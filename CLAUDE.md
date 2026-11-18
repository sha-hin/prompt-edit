# PromptEdit: Pixelforge image generation

A take-home assessment for PromptEdit (Senior Product Engineer). Live at https://prompt-edit-production-lhbern.laravel.cloud.

Users sign in and generate images through Pixelforge, a simulated async image API. They pay Pixelforge's cost plus a 25% markup, recorded exactly once per generation. Failed generations are free. Correct money comes first, then robustness, UX and code quality.

## Read these first

- `docs/assessment.md`: the brief, the requirements, the stretch goals and what gets graded.
- `docs/pixelforge-api.md`: the Pixelforge API. If it disagrees with https://pixelforge.promptedit.dev/docs, the live docs win.
- `.claude/skills/laravel-best-practices/SKILL.md`: the code rules for this repo. Follow it for every backend change.

## Stack

- Laravel 13 on PHP 8.4, Inertia v3 + Vue 3 + TypeScript, Tailwind 4, Fortify auth, Wayfinder routes.
- MySQL for the app and the tests, so locks and unique keys behave as in production.
- The queue, cache and sessions use the database driver.
- Pest 5, Larastan level 8, Pint, Rector, and architecture tests.

## Commands

```bash
docker compose up -d                  # full dev stack: app, queue worker, Vite, MySQL
composer setup                        # or without Docker: install, .env, key, migrate, build
composer dev                          # app, queue worker, logs and Vite together
php artisan db:seed                   # demo user and admin (config/demo.php)

composer test                         # Pint check, Larastan, Rector dry-run, Pest
composer ci:check                     # the same plus the frontend checks, what CI runs
composer lint                         # fix code style
composer test:mutate                  # mutation testing for money and pricing
```

Tests need a MySQL database named `prompt_edit_test`. `phpunit.xml` forces fake Pixelforge credentials and a fake base URL, and `tests/Pest.php` blocks stray HTTP, so no test can call the real API.

## How it works

1. **Estimate.** `GenerationEstimator` prices the minimum and maximum from cached `/pricing`, using the same `PriceCalculator` as the actual charge.
2. **Create.** `CreateGeneration` locks the user row and checks that the balance, minus every running generation's maximum estimate, covers this generation's maximum. It then saves the row with a stored `Idempotency-Key` and dispatches `SubmitGeneration` after commit.
3. **Submit.** `SubmitGeneration` (unique per generation, on the `pixelforge` queue) sends the create request with `metadata.generation_id`, releases on 429 or 503 for as long as `Retry-After` asks, and fails for free on errors that can't succeed on retry.
4. **Results.** `POST /webhooks/pixelforge` verifies the signature on the raw body, then `ProcessPixelforgeEvent` stores and applies the event in one transaction. `ApplyGenerationUpdate` only moves statuses forward, charges on success with the rates locked at create, and writes the ledger entry in the same transaction.
5. **Safety net.** `generations:sweep`, every minute, resubmits stuck pending generations and polls those with no webhook.
6. **Admin report.** Spend, cost and profit per UTC completion day and per user, reconciled with `GET /v1/usage` to the nano-dollar.

## Decisions

- **Money:** integer nano-dollars (`App\Money\Money`). Rates must be whole nano-dollars per token, or `InvalidRate` is thrown. The 25% markup is rounded once, half up, to one nano-dollar.
- **Exactly once:** `credit_transactions.generation_id` is unique. A purchase or grant is unique on `(user_id, idempotency_key)`.
- **A charge is never refused** after an image exists, so a balance can end slightly below zero.
- **Webhooks are processed in the request**, not in a job. The work is small, all-or-nothing, and redelivered on a 5xx.
- **Deleted users keep their generations** (`nullOnDelete`), so the books still match Pixelforge.

## Pixelforge environment

- Use one API key per environment. Each key has its own webhook URL, signing secret and `/v1/usage`.
- Set `PIXELFORGE_API_KEY`, `PIXELFORGE_WEBHOOK_SECRET` and `PIXELFORGE_BASE_URL`, read through `config('services.pixelforge.*')`.
- For local webhooks, use a tunnel (Expose, ngrok or Cloudflare Tunnel).
- Force each failure path with the prompt keywords `[fail]`, `[slow]`, `[duplicate]`, `[blocked]`, `[overloaded]` and `[succeed]`.
