# TEMACONCEPT

Site web réalisé dans le cadre d'un stage : présentation de l'entreprise, services, réalisations, formulaire de contact et assistant Lina.

Technologies : React, TypeScript, Laravel et SQLite.

## Prérequis

- Node.js 22.12 ou supérieur
- PHP 8.2 ou supérieur, avec `pdo_sqlite` et `sqlite3`
- Composer 2

## Installation

Dans le dossier du projet :

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve
```

Sur Linux ou macOS, remplacer `copy` par `cp`.

Dans un deuxième terminal, depuis le dossier du projet :

```bash
cd frontend
npm ci
npm run dev
```

Ouvrir `http://localhost:5173`. Le serveur Laravel doit rester lancé sur le port 8000.

Lina fonctionne sans clé API. Pour utiliser OpenAI, renseigner `OPENAI_API_KEY` dans `backend/.env`.
