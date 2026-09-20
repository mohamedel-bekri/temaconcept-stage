<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\Chatbot\ChatPipeline;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\ServicesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ServicesSeeder::class, KnowledgeSeeder::class, UserSeeder::class]);
    }

    public function test_greeting_creates_session_and_returns_reply(): void
    {
        $pipeline = app(ChatPipeline::class);

        $result = $pipeline->handle('uuid-greeting', 'Bonjour');

        $this->assertSame('greeting', $result['intent']);
        $this->assertStringContainsString('Lina', $result['reply']);
        $this->assertNotEmpty($result['quick_replies']);
        $this->assertDatabaseHas('chat_sessions', ['session_uuid' => 'uuid-greeting']);
        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'Bonjour']);
        $this->assertDatabaseHas('messages', ['role' => 'assistant']);
    }

    public function test_lead_conversation_extracts_fields_and_scores(): void
    {
        $pipeline = app(ChatPipeline::class);

        $pipeline->handle('uuid-lead', 'Bonjour, je m\'appelle Karim Alami et je travaille chez Atlas Logistique.');
        $pipeline->handle('uuid-lead', 'Nous avons besoin d\'une application mobile pour nos livreurs. Budget entre 100000 et 150000 DH, c\'est urgent.');
        $pipeline->handle('uuid-lead', 'Mon email est karim@atlas.ma. Oui, vous pouvez me contacter.');

        $lead = Lead::query()->where('session_uuid', 'uuid-lead')->firstOrFail();

        $this->assertSame('Karim Alami', $lead->name);
        $this->assertSame('Atlas Logistique', $lead->company);
        $this->assertStringContainsString('application mobile', $lead->need);
        $this->assertStringContainsString('100000', $lead->budget);
        $this->assertSame('urgent', $lead->timeline);
        $this->assertGreaterThanOrEqual(40, $lead->score);
        $this->assertSame('contacted', $lead->status);
        $this->assertFalse($lead->escalated);
    }

    public function test_human_request_escalates_and_notifies_admin(): void
    {
        $pipeline = app(ChatPipeline::class);

        $pipeline->handle('uuid-escalate', 'Pouvez-vous me mettre en relation avec un conseiller humain ?');
        $pipeline->handle('uuid-escalate', 'Mon numéro est 0612345678. Oui, vous pouvez me contacter.');

        $lead = Lead::query()->where('session_uuid', 'uuid-escalate')->firstOrFail();
        $admin = User::query()->where('email', 'admin@temaconcept.com')->firstOrFail();

        $this->assertTrue($lead->escalated);
        $this->assertSame('qualified', $lead->status);
        $this->assertSame('escalated', $lead->chatSessions()->first()->status);
        $this->assertGreaterThanOrEqual(1, $admin->notifications()->count());
    }

    public function test_consecutive_calls_reuse_same_session(): void
    {
        $pipeline = app(ChatPipeline::class);

        $pipeline->handle('uuid-same', 'Bonjour');
        $pipeline->handle('uuid-same', 'Merci');

        $this->assertDatabaseCount('chat_sessions', 1);
        $this->assertSame(2, \App\Models\ChatSession::query()->where('session_uuid', 'uuid-same')->first()->message_count);
    }

    public function test_service_selection_returns_details_instead_of_the_service_list(): void
    {
        $pipeline = app(ChatPipeline::class);
        $pipeline->handle('service-details', 'Découvrir les services');
        $result = $pipeline->handle('service-details', 'Logiciel sur mesure');

        $this->assertStringContainsString('facturation', $result['reply']);
        $this->assertStringNotContainsString('Nos principaux domaines', $result['reply']);
        $this->assertContains('Et le prix ?', $result['quick_replies']);
    }

    public function test_price_follow_up_keeps_the_selected_service(): void
    {
        $pipeline = app(ChatPipeline::class);
        $pipeline->handle('mobile-price', 'Application mobile');
        $result = $pipeline->handle('mobile-price', 'Et le prix ?');

        $this->assertStringContainsString('applications mobiles', $result['reply']);
        $this->assertStringContainsString('pas d’un tarif fixe', $result['reply']);
        $otherSession = $pipeline->handle('other-price', 'Et le prix ?');
        $this->assertStringNotContainsString('applications mobiles', $otherSession['reply']);
    }

    public function test_explicit_price_question_takes_priority_over_project_intent(): void
    {
        $result = app(ChatPipeline::class)->handle('project-price', 'Je veux une application mobile, quel est le prix ?');

        $this->assertSame('pricing', $result['intent']);
        $this->assertStringContainsString('pas d’un tarif fixe', $result['reply']);
    }

    public function test_follow_up_uses_the_new_service_after_a_topic_change(): void
    {
        $pipeline = app(ChatPipeline::class);
        $pipeline->handle('topic-change', 'Application mobile');
        $pipeline->handle('topic-change', 'Infrastructure informatique');
        $result = $pipeline->handle('topic-change', 'Plus de détails');

        $this->assertStringContainsString('Cloud hybride', $result['reply']);
        $this->assertStringNotContainsString('App Store', $result['reply']);
    }

    public function test_maintenance_question_uses_the_relevant_knowledge(): void
    {
        $result = app(ChatPipeline::class)->handle('maintenance', 'Proposez-vous la maintenance du logiciel ?');

        $this->assertStringContainsString('support@temaconcept.com', $result['reply']);
        $this->assertStringNotContainsString('Nos principaux domaines', $result['reply']);
    }

    public function test_contact_data_is_not_saved_without_explicit_consent(): void
    {
        app(ChatPipeline::class)->handle('uuid-consent', 'Mon email est visiteur@example.com.');

        $this->assertDatabaseMissing('leads', ['session_uuid' => 'uuid-consent']);
    }
}
