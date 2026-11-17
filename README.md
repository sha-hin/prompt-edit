# PromptEdit

Image generation on Pixelforge, with every generation billed at exactly what it cost plus a 25% markup. Built with Laravel 13, Inertia, Vue 3 and TypeScript.

## Setup

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
