---
name: laravel-best-practices
description: Follow when writing, structuring or reviewing Laravel code in this repo (PromptEdit/Pixelforge): code placement, money, webhooks, jobs, external APIs, state, concurrency, tests and review.
---

# Laravel best practices

Apply these rules to all backend code in this project.

A design pattern goes in only when the code needs it. Never add one for its own sake.

## 1. Where code goes

Folders follow the **domain**, not the kind of class. Code for a new feature goes next to the rest of its domain.

```
app/
├── Generations/   CreateGeneration, RecordGenerationResult, States/ ... (one action per use case)
├── Money/         Money value object (+ cast)
├── Pricing/       estimate + actual cost, markup
├── Pixelforge/    the client interface, HTTP adapter, throttling decorator, exceptions, event factory
├── Billing/       ledger, balances, usage reconciliation
├── Http/          Controllers, Requests, Resources, Middleware: HTTP only
└── Jobs/ Events/ Listeners/ Policies/ Console/ Models/ Providers/ Enums/ Casts/
```

| Kind of code                              | Where it goes                         | Rule                                                                       |
| ----------------------------------------- | ------------------------------------- | -------------------------------------------------------------------------- |
| Who may do it                             | a Policy                              | Never an `if` in a controller                                              |
| Is the input valid                        | a Form Request                        | Shape and simple rules only. A business rule asks the model or the action. |
| One thing a user or system does           | an action in its domain folder        | One public method (`handle`). It owns its transaction and its locks.       |
| What a value is                           | a value object (`Money`)              | Immutable, and validated when it's created                                 |
| Relations, casts, query rules             | the model                             | Rules that are queries become scopes                                       |
| Reactions that don't depend on each other | an event and its listeners            | Each listener can fail on its own                                          |
| Anything slow or external                 | a queued job, dispatched after commit | Always set tries, backoff, a timeout and `failed()`                        |

- Controllers are thin: Request → Policy → one action → Resource or Inertia response. They make no queries and open no `DB::transaction()` (enforced by an arch test).
- No `XxxService` that collects unrelated methods.
- A repository only appears when two stores really answer the same question.

## 2. Class conventions

- `final class` by default. Use `final readonly class` for value objects, DTOs, events and stateless collaborators.
- Full type declarations everywhere: parameters, returns and properties. Add PHPDoc generics for arrays (`@return list<int>`, `@param array<string, mixed>`, `array{id: string, ...}`), because Larastan runs at level 8.
- Dependencies come in through the constructor. Never `new` a service inside a class, and never call `app()` inside a method. Jobs receive their dependencies as arguments to `handle()`.
- Bind interfaces in `AppServiceProvider::register()`. A third-party SDK or client is a `singleton`, configured from `config/services.php`. `env()` is read only in config files.
- `AppServiceProvider::boot()` sets two things (both are already in place):
    - `Date::use(CarbonImmutable::class)`
    - `Model::shouldBeStrict(! app()->isProduction())`
- Models:
    - Declare casts in `protected function casts(): array`, with enums cast to their enum class.
    - Keep `$fillable` to the fields users may set. System fields such as status, costs and timestamps are written with `forceFill()`.
- Domain exceptions:
    - Extend `RuntimeException` or `LogicException`, with named constructors (`InvalidTransition::from(...)`, `GenerationNotFound::forUser(...)`).
    - When the user should see the error, the exception knows its HTTP status and implements `render()`, so actions never mention HTTP.
- Enums are backed `string` enums in `app/Enums` (enforced by an arch test).
- Comments explain _why_, at the end of the line, short.

## 3. Money

Rules:

- Money is an **integer** in a minor unit, carried with its currency. Never a float, and never a float string pushed through float arithmetic.
- Round **once**, at a defined step. Then the books match the provider exactly.
- Percentages are basis points: 1000 = 10%. Round half up, away from zero, with `intdiv`.
- `format()` is for people only. Never feed its output back into a calculation.

This project:

- Pixelforge costs are fractions of a cent. `cost_usd` has 9 decimals and rates are per 1M tokens.
- So the minor unit must be smaller than a cent. Choose it so that every provider cost is an exact integer (for example nano-dollars, 1e-9 USD). Parse decimal strings without floats, using string math or `bcmath`.
- Decide in one documented place where the 25% markup is rounded and to what unit.

Shape:

```php
final readonly class Money
{
    private function __construct(public int $amount, public Currency $currency) {}

    public static function of(int $amount, Currency|string $currency): self { /* ... */ }
    public function add(self $other): self { /* assertSameCurrency, return new self */ }
    public function times(int $factor): self { /* ... */ }
    public function percent(int $basisPoints): self
    {
        $product = $this->amount * $basisPoints;
        $rounded = intdiv(abs($product) + 5_000, 10_000);

        return new self($product < 0 ? -$rounded : $rounded, $this->currency);
    }
    public function equals(self $other): bool { /* ... */ }
}
```

- Store money with a `MoneyCast` that writes the amount column and the currency together. Setting anything except `Money` throws.
- Build a price once, step by step. Show the estimate and record the actual cost from the same calculator, not from two copies of the formula.
- Store the rates that were used: Pixelforge locks `rates` when the generation is created, so store them with it.

## 4. Webhooks

Order of work: verify → store → answer 200 → process in a job.

- **Verify** against the **raw body** (`$request->getContent()`):
    - Compare signatures in constant time with `hash_equals`.
    - Reject timestamps older than the allowed window.
    - Parse JSON only after the signature passes.
    - Invalid signature → 400.
- **Store** in `webhook_events`, with a unique key on `(provider, event_id)`. Create the row with `WebhookEvent::createOrFirst([...keys], [...values])` and dispatch the job only when `$webhook->wasRecentlyCreated`. A repeated delivery is then harmless.
- **Answer** `200` fast. Never do slow work in the request; Pixelforge counts as success only a 2xx within 10s.
- **Process** in a job (`ProcessXWebhook`):
    - `$tries`, and `backoff()` returning a list of seconds.
    - `failed()` marks the event failed with the error.
    - Mark the event processed even for event types you don't act on.
- A factory turns the payload into typed event objects (`final readonly class`) with `match ($payload['type'])`. Unknown types return `null`.
- **Out of order**:
    - Apply an event only if it is newer than what you already applied, under `lockForUpdate()` in a transaction.
    - Final states never move back.
    - Something that arrives for an unknown record is stored (a tombstone), not thrown away.
- **Exactly once side effects**:
    - Use an atomic claim: `Model::whereKey($id)->where('status', Pending)->update([...])`, and only the winner (affected rows = 1) acts.
    - For money, the recorded charge comes from a unique constraint or an atomic claim, never from check-then-insert.
- Keep a polling fallback for when delivery fails, from a scheduled command.

## 5. Lifecycles: State

- A lifecycle with rules is states plus allowed transitions, not booleans scattered through the code.
- An abstract `XState` has one method per transition. Each one throws `InvalidTransition::from($model, 'action')` by default, and each concrete state overrides only the transitions it allows.
- A transition returns `bool`: `true` when the model changed, `false` when it was already in the target state (idempotent).
- Time-based changes, such as a stuck generation that times out, need a scheduler, never "when someone opens the page".

## 6. External API clients

- One **interface** per external service (`PixelforgeClient`) with an HTTP **adapter** (`HttpPixelforgeClient`), bound in the container. Tests bind a fake.
- Map responses into typed objects at the edge. The rest of the app never sees raw arrays.
- Errors are typed exceptions (`PixelforgeRequestFailed`, `PixelforgeRateLimited` with `$retryAfter` seconds).
    - Parse `Retry-After` as either seconds or an HTTP date. If it can't be read, wait a default, never 0.
- **Retry only what can succeed on retry**:
    - Retry 429, 503 and timeouts with backoff, and honour `Retry-After`.
    - Never retry a 400 or 422.
- Limits that span workers live in Redis (`RateLimiter::hit($key, decaySeconds: 60)`), wrapped in a **decorator** that implements the same interface (`ThrottledPixelforgeClient`).
- Count concurrency per account, not per worker.
- In jobs:
    - Catch the rate-limit exception and call `$this->release($e->retryAfter)`. That puts the job back on the queue and is not a failure.
    - Use `retryUntil()` when the number of releases is unknown.
- Make create calls safe to retry with an `Idempotency-Key` that you store before sending.
- Slow outside work gets its own queue, so it never blocks other jobs.
- Dispatch with `->afterCommit()` whenever the job depends on rows written in the current transaction.

## 7. Concurrency and transactions

- A check-then-insert is a race. Either lock what you check (`lockForUpdate()` inside `DB::transaction`) or let a unique key decide.
- Prove races with a real concurrent test on the real database. Tests run on MySQL (`phpunit.xml`), because SQLite hides races.
- Keep transactions short. External HTTP calls never happen inside a transaction.

## 8. Tenancy and access

- Every query starts from the current user or account (`$user->generations()`), never `Generation::find($id)`.
- Another user's id answers 404, exactly like an id that doesn't exist.
- Use policies for permission. No mass assignment of ownership or status fields.

## 9. Reports and exports

- Read large tables in chunks (`lazyById()` or `chunkById()`) and stream the output.
- Compare two big lists, such as your ledger against `GET /v1/usage`, as streams.
- Count queries per page:
    - A query count that grows with the number of rows is an N+1.
    - Fix it with `with()`, `withCount()` and `ofMany()`, and test with more than one row.

## 10. Tests (Pest)

- `tests/Pest.php` setup (already in place) for Pest-style tests in `Feature` and `Unit`:
    - `RefreshDatabase`.
    - A frozen clock: `$this->travelTo(CarbonImmutable::parse('...', 'UTC'))`.
    - `Http::preventStrayRequests()`.
- `phpunit.xml` forces fake Pixelforge credentials and base URL, so tests can never reach the live provider.
- Swap external services through the container (a fake implementation) or with `Http::fake()`.
- Write new tests in Pest style. The starter kit's class-based auth and settings tests stay as they are.
- Every bug fix gets a test named after the behaviour (`it('records a duplicate webhook once')`).
- Use datasets (`->with([...])`) for tables of cases, such as the cost per quality and size.
- Money and pricing classes get `mutates(Class::class)`, run with `composer test:mutate`.
- Webhook tests send the real signed raw body plus the duplicate, out-of-order and old-timestamp cases.

## 11. Checks before every push

`composer test` runs all of them locally; CI (`.github/workflows/tests.yml`) runs the same plus the frontend checks.

- **Pint**: `pint.json` with preset `laravel` and `concat_space: one`. Run `composer lint` to fix.
- **Larastan** level 8 (`phpstan.neon`), with a baseline only if one is needed.
- **Arch tests** in `tests/Architecture/ArchTest.php`:
    - No `dd`, `dump` or `ray`.
    - `env()` only in config.
    - Controllers don't use `DB`.
    - Models extend `Model`.
    - Enums are string-backed.
    - No static state in app classes.
- **Rector** (`rector.php`: PHP sets, Laravel composer-based sets, `deadCode`, `typeDeclarations`). CI runs `--dry-run`.
- **Pest**, run with `--parallel`.

## 12. Review checklist

Review the behaviour, not the style:

- What happens with another user's id?
- What happens on a duplicate request or webhook?
- What happens on an out-of-order webhook?
- What happens on a 429, a 503, or a failure after the generation was accepted?
- Is any money a float, or rounded twice?
- Is a date mutable?
- Does a job run before its transaction commits?
- Which test would have caught each bug? Green CI only proves the tests that exist.
