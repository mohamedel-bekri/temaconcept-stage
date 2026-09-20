<?php

namespace App\Services\Chatbot;

use App\Models\KnowledgeChunk;
use App\Models\Service;
use App\Support\TextNormalizer;

class MockEngine
{
    public function reply(string $intent, array $context = []): array
    {
        $history = $context['history'] ?? [];
        $last = $history === [] ? '' : $history[array_key_last($history)]['content'];
        $text = implode(' ', TextNormalizer::tokens($last));
        $specific = $this->specificReply($text, $intent, $history);
        if ($specific !== null) {
            return $specific;
        }

        $company = config('chatbot.company');
        $content = match ($intent) {
            'greeting' => 'Bonjour, je suis Lina. Je peux répondre à vos questions sur nos services, les devis ou notre accompagnement. Que recherchez-vous ?',
            'services' => $this->servicesReply(),
            'pricing' => 'Le budget dépend du périmètre, des intégrations et du niveau d’accompagnement. Nous préparons un devis détaillé sous 48 h après avoir compris votre besoin. Quel type de solution souhaitez-vous mettre en place ?',
            'contact' => sprintf("Vous pouvez nous joindre au %s ou à %s. Nos bureaux sont à %s, du lundi au vendredi de 9 h à 18 h.", $company['phone'], $company['email_contact'], $company['address']),
            'about' => sprintf('TEMACONCEPT est une société d’ingénierie informatique basée à Témara. Depuis %d ans, nous accompagnons les entreprises sur leurs logiciels, applications, infrastructures et projets data / IA.', $company['years']),
            'careers' => 'Vous pouvez envoyer votre CV et une courte présentation à contact@temaconcept.com, avec l’objet « Candidature ». Nous étudierons votre profil.',
            'process' => "Notre méthode suit quatre étapes : cadrage, conception, réalisation puis accompagnement en production. Chaque étape est validée avec vos équipes avant de passer à la suivante.",
            'lead' => 'Nous pouvons vous accompagner sur ce type de projet. Pour vous orienter correctement, quelle est la fonction principale que votre solution doit remplir ?',
            'human' => 'Bien sûr. Vous pouvez laisser votre email ou votre numéro de téléphone ; je vous demanderai ensuite votre accord avant de le transmettre à notre équipe.',
            'thanks' => 'Avec plaisir. Je reste disponible si vous avez une autre question.',
            default => $this->defaultReply($context),
        };
        return ['content' => $content, 'quick_replies' => $this->quickRepliesFor($intent)];
    }

    public function quickRepliesFor(string $intent): array
    {
        return match ($intent) {
            'greeting' => ['Découvrir les services', 'Demander un devis', 'Nos coordonnées'],
            'services' => ['Logiciel sur mesure', 'Application mobile', 'Infrastructure informatique'],
            'pricing' => ['Décrire mon besoin', 'Nos coordonnées'],
            'human' => ['Laisser mon email', 'Laisser mon numéro'],
            'about', 'process' => ['Découvrir les services', 'Demander un devis'],
            default => ['Découvrir les services', 'Demander un devis'],
        };
    }

    private function servicesReply(): string
    {
        $services = Service::query()->active()->orderBy('order')->get();
        if ($services->isEmpty()) return 'Nous intervenons sur les logiciels sur mesure, les applications mobiles, l’intégration, l’infrastructure, la gestion de projets et la data / IA.';
        $names = $services->take(6)->pluck('name')->implode(', ');
        return "Nos principaux domaines sont : {$names}. Lequel correspond le mieux à votre besoin ?";
    }

    private function specificReply(string $text, string $intent, array $history): ?array
    {
        if (in_array($intent, ['human', 'contact', 'careers', 'thanks', 'greeting', 'about'], true)) {
            return null;
        }

        $topics = [
            'maintenance-apres-livraison' => '/\b(maintenance|support|apres livraison|mise a jour|mises a jour)\b/u',
            'technologies-utilisees' => '/\b(technologie|technologies|langage|langages|framework|frameworks|stack|flutter|react native|laravel|python)\b/u',
            'delai-de-realisation' => '/\b(delai|delais|duree|combien de temps|quand|semaines)\b/u',
            'methode-de-travail' => '/\b(etapes|methode|deroulement|comment ca se passe)\b/u',
        ];
        foreach ($topics as $slug => $pattern) {
            if (preg_match($pattern, $text)) {
                $chunk = KnowledgeChunk::query()->where('active', true)->where('slug', $slug)->first();
                if ($chunk) {
                    return [
                        'content' => $chunk->content,
                        'quick_replies' => ['Demander un devis', 'Parler à un conseiller'],
                    ];
                }
            }
        }

        $service = $this->findService($text);
        $followUp = (bool) preg_match('/\b(et le prix|et pour|plus de details|en savoir plus|exemple|exemples|fonctionnalites|comment commencer|decrire mon besoin)\b/u', $text);
        if ($service === null && ($followUp || $intent === 'pricing')) {
            foreach (array_reverse(array_slice($history, 0, -1)) as $message) {
                if ($message['role'] !== 'user') {
                    continue;
                }
                $previous = implode(' ', TextNormalizer::tokens($message['content']));
                $service = $this->findService($previous);
                if ($service !== null) {
                    break;
                }
                // Ne pas reprendre un sujet antérieur à un changement de conversation.
                if (preg_match('/\b(bonjour|merci|contact|stage|recrutement)\b/u', $previous)) {
                    break;
                }
            }
        }

        if ($service === null) {
            return null;
        }

        if ($intent === 'pricing') {
            return [
                'content' => 'Pour « '.$service->name.' », le prix dépend des fonctionnalités, des intégrations et de l’accompagnement demandé. Je ne dispose pas d’un tarif fixe. Quelle fonctionnalité est indispensable pour une première version ?',
                'quick_replies' => ['Comment commencer ?', 'Parler à un conseiller'],
            ];
        }

        $bullets = implode("\n", array_map(fn ($item) => '• '.$item, $service->bullets ?? []));
        $content = $service->name."\n\n".$service->description;
        if ($followUp && $bullets !== '') {
            $content = 'Voici ce que comprend ce service :'."\n".$bullets;
        } elseif ($bullets !== '') {
            $content .= "\n\n".$bullets;
        }
        if (str_contains($text, 'comment commencer') || str_contains($text, 'decrire mon besoin')) {
            $content = 'Pour démarrer sur « '.$service->name.' », décrivez les utilisateurs concernés, la tâche à simplifier et les outils déjà utilisés. Quelle tâche souhaitez-vous améliorer en priorité ?';
        }

        return [
            'content' => $content,
            'quick_replies' => ['Quelles technologies ?', 'Et le prix ?', 'Comment commencer ?'],
        ];
    }

    private function findService(string $text): ?Service
    {
        $patterns = [
            'applications-mobiles' => '/\b(mobile|mobiles|android|ios|livreur|livreurs|livraison|flotte)\b/u',
            'integration-systemes' => '/\b(integration|interconnecter|connecter|api|apis|webhook|webhooks|ressaisie|synchroniser)\b/u',
            'infrastructure' => '/\b(infrastructure|reseau|reseaux|serveur|serveurs|cloud|sauvegarde|securite)\b/u',
            'data-ia' => '/\b(data|ia|intelligence artificielle|chatbot|machine learning|bi|analyse de donnees)\b/u',
            'gestion-projets' => '/\b(gestion de projet|gestion de projets|chef de projet|pilotage)\b/u',
            'logiciels-sur-mesure' => '/\b(logiciel|logiciels|sur mesure|erp|crm|stock|facturation|site web|portail)\b/u',
        ];
        foreach ($patterns as $slug => $pattern) {
            if (preg_match($pattern, $text)) {
                return Service::query()->active()->where('slug', $slug)->first();
            }
        }

        return null;
    }

    private function defaultReply(array $context): string
    {
        $chunk = $context['chunk'] ?? null;
        if ($chunk instanceof KnowledgeChunk && filled($chunk->content)) return mb_substr($chunk->content, 0, 900);
        return 'Je n’ai pas cette information avec certitude. Je peux répondre sur nos services, nos devis et notre accompagnement, ou vous orienter vers un conseiller.';
    }
}
