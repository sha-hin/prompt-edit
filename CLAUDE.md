# PromptEdit: Pixelforge image generation

A take-home assessment for PromptEdit (Senior Product Engineer).

Users sign in and generate images through Pixelforge, a simulated async image API. They pay Pixelforge's cost plus a 25% markup, recorded exactly once per generation. Failed generations are free.

The assessment ranks the work in this order: correct money, robustness, UX, code quality. Correctness comes before polish.

## Read these first

- `docs/assessment.md`: the brief, the requirements, the stretch goals and what gets graded.
- `docs/pixelforge-api.md`: the Pixelforge API (endpoints, pricing and token tables, errors, limits, idempotency, webhook signing and delivery, test keywords). If it disagrees with the live docs at https://pixelforge.promptedit.dev/docs, the live docs win.
- `.claude/skills/pattern-playbook-laravel/SKILL.md`: the code rules for this repo, from the Pattern Playbook course. Follow it for every backend change, covering folders, money, webhooks, jobs, external clients, states, tests and review.

## Stack

- Laravel 13 on PHP 8.4, Inertia v3 + Vue 3 + TypeScript, Tailwind 4.
- Laravel Fortify handles auth (from the official Vue starter kit, with all auth features kept).
- Wayfinder generates typed routes for the frontend (`resources/js/routes`, `resources/js/actions`). These are generated, gitignored, and rebuilt by the Vite plugin.
- MySQL is the database for both the app and the tests, so races and locks behave as in production. Queues and cache use the database driver by default. Switch them to Redis when rate limiting across workers needs it.
- Pest 5 for tests, Larastan level 8, Pint, Rector.

## Commands

```bash
composer install && npm install       # first run on a new machine
cp .env.example .env && php artisan key:generate
php artisan migrate
composer dev                          # app, queue worker, logs and Vite together

composer test                         # Pint check, Larastan, Rector dry-run, Pest (what CI runs, minus the frontend)
composer lint                         # fix code style
composer refactor                     # apply Rector
composer test:mutate                  # mutation testing (money and pricing classes use mutates())
npm run check && npm run types:check  # frontend lint/format and vue-tsc
```

Tests need a MySQL database named `prompt_edit_test`. `phpunit.xml` forces fake Pixelforge credentials and a fake base URL, and `tests/Pest.php` blocks stray HTTP. A test therefore can never call the real API.

## Pixelforge environment

- Create one API key per environment (local and deployed). Each key has its own webhook URL, signing secret and usage report.
- Set them in `.env` as `PIXELFORGE_API_KEY`, `PIXELFORGE_WEBHOOK_SECRET` and `PIXELFORGE_BASE_URL`, read through `config('services.pixelforge.*')`. Never call `env()` outside `config/`.
- For local webhooks, expose the app with a tunnel (Expose, ngrok or Cloudflare Tunnel) and set that URL on the local key.
- Use the prompt keywords `[fail]`, `[slow]`, `[duplicate]`, `[blocked]`, `[overloaded]` and `[succeed]` to force each failure path on purpose.

## Current state

- So far this is only the scaffold: the starter kit, the tooling, the conventions and these docs.
- There is no domain code yet: no generations, pricing, webhooks or billing.
- Domain code goes in domain folders under `app/` (see the skill, section 1).

## Decisions still open (decide before writing the money code)

- **The money unit.** Pixelforge costs have 9 decimals, so integer cents can't hold them exactly.
- **Where and how the 25% markup is rounded.**
- **How an estimate becomes the actual charge, exactly once.** Webhook and polling may both report the same completion.
- **Whether to do the stretch goals:** the admin report reconciled with `GET /v1/usage`, and balances.
