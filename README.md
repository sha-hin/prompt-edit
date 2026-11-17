# PromptEdit

Image generation on Pixelforge, with every generation billed at exactly what it cost plus a 25% markup. Built with Laravel 13, Inertia, Vue 3 and TypeScript.

## Setup with Docker

Needs only Docker (or OrbStack). PHP, Composer, Node and MySQL all run in containers.

```bash
docker compose up -d
```

The first run creates `.env`, installs dependencies, generates the app key and migrates. Then:

- App: http://localhost:8000 (Vite dev server on :5173, queue worker running)
- MySQL: `localhost:3306`, user `root`, password `secret`, databases `prompt_edit` and `prompt_edit_test`

Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` in `.env`, then `docker compose restart app queue`.

Run any command inside the stack:

```bash
docker compose exec app composer test
docker compose exec app php artisan tinker
docker compose exec app npm run check
docker compose logs -f app queue vite
```

## Setup without Docker

Requirements:

- PHP 8.4
- Composer
- Node 22
- MySQL 8

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Then:

1. Create the `prompt_edit` and `prompt_edit_test` databases.
2. Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` in `.env`.
3. Run:

```bash
php artisan migrate
composer dev
```

## Checks

```bash
composer test          # Pint, Larastan (level 8), Rector dry-run, Pest
npm run check          # frontend lint and format
npm run types:check    # vue-tsc
```

CI runs all of them on every push and pull request.

## Docs

- [`docs/assessment.md`](docs/assessment.md): the brief
- [`docs/pixelforge-api.md`](docs/pixelforge-api.md): Pixelforge API notes
- [`CLAUDE.md`](CLAUDE.md): working notes and the commands
