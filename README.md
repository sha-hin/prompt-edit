# PromptEdit

Image generation on Pixelforge, with every generation billed at exactly what it cost plus a 25% markup. Built with Laravel 13, Inertia, Vue 3 and TypeScript.

## Live app

**https://prompt-edit-production-lhbern.laravel.cloud**

- The login page lists the demo user and admin accounts, with a button that fills in the form. The admin sees the report under **Admin**.
- New accounts start verified, with $10 of free credit. **Buy credits** adds more (a fake purchase).
- Webhook endpoint: `https://prompt-edit-production-lhbern.laravel.cloud/webhooks/pixelforge`

## Setup with Docker

Needs only Docker (or OrbStack). PHP, Composer, Node and MySQL all run in containers.

```bash
docker compose up -d
```

The first run creates `.env`, installs dependencies, generates the app key, migrates and seeds the demo accounts. Then:

- App: http://localhost:8000 (Vite dev server on :5173, queue worker running)
- MySQL: `localhost:3306`, user `root`, password `secret`, databases `prompt_edit` and `prompt_edit_test`

Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` in `.env`, then `docker compose restart app queue`.

### Demo accounts

`php artisan db:seed` creates these, each with the $10 of free credit every new account gets. The login page lists them with a button that fills in the form.

| Role  | Email                   | Password              |
| ----- | ----------------------- | --------------------- |
| User  | `user@promptedit.test`  | `demo-user-password`  |
| Admin | `admin@promptedit.test` | `demo-admin-password` |

The seeder is safe to rerun on every deploy. Change the emails and passwords with `DEMO_USER_*` and `DEMO_ADMIN_*`, or hide the list with `DEMO_ACCOUNTS=false` (see `config/demo.php`). Make any other user an admin with `php artisan users:make-admin {email}`.

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

## Deploy (Laravel Cloud)

The live app runs on [Laravel Cloud](https://cloud.laravel.com), built straight from `main`.

1. **App:** PHP 8.4, Node 22, repository `sha-hin/prompt-edit`, branch `main`.
2. **Database:** Laravel MySQL. The reports use MySQL date and window functions, so don't pick Postgres. Cloud fills in the `DB_*` variables.
3. **Build commands:**
    ```bash
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    ```
4. **Deploy commands** (seeding only creates or resets the demo accounts, so it is safe on every deploy):
    ```bash
    php artisan migrate --force
    php artisan db:seed --force
    ```
5. **Scheduler:** on. It runs `generations:sweep` every minute, which catches generations whose webhook never arrived.
6. **Background process:** `php artisan queue:work --queue=pixelforge,default --max-time=3600`.
7. **Hibernation:** off, or webhooks, the worker and the sweep stop while the app sleeps.
8. **Environment** (`APP_KEY` and `DB_*` come from Cloud):
    ```dotenv
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://prompt-edit-production-lhbern.laravel.cloud
    QUEUE_CONNECTION=database
    CACHE_STORE=database
    SESSION_DRIVER=database
    INERTIA_SSR_ENABLED=false
    DEMO_ACCOUNTS=true
    AUTH_VERIFY_EMAIL=false        # no mail is set up, so new accounts start verified
    PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
    PIXELFORGE_API_KEY=            # a key made only for production
    PIXELFORGE_WEBHOOK_SECRET=     # that key's signing secret
    ```
9. **Pixelforge:** create a separate API key for production, so its `/v1/usage` holds only the live app's generations and the admin Overview matches it exactly. Set that key's webhook URL to `https://prompt-edit-production-lhbern.laravel.cloud/webhooks/pixelforge` and send a test event; it should get `200 {"received": true}`.

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
