<?php

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Generations\ApplyGenerationUpdate;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PixelforgeGeneration;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

const TEST_GENERATION_ID = 'gen_01M7N6J8PJWNEKE6XW84S74ZV9';

/**
 * A Pixelforge event wrapping a generation, as delivered.
 *
 * @param  array<string, mixed>  $generation
 * @return array<string, mixed>
 */
function pixelforgeEvent(string $type, array $generation = [], string $id = 'evt_01'): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'type' => $type,
        'created_at' => '2026-11-17T09:59:00Z',
        'data' => ['object' => $generation],
    ];
}

/**
 * POST $body to the webhook, signed the way Pixelforge signs it.
 *
 * @param  array<string, mixed>|string  $body
 * @return TestResponse<Response>
 */
function deliver(array|string $body, ?int $timestamp = null, string $secret = 'whsec_test_not_a_real_secret', ?string $signedBody = null): TestResponse
{
    $raw = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', $timestamp . '.' . ($signedBody ?? $raw), $secret);

    return test()->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $raw);
}

function acceptedGeneration(): Generation
{
    return Generation::factory()->queued()->create(['pixelforge_id' => TEST_GENERATION_ID]);
}

it('marks a generation processing', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.processing', pixelforgeJson('generation-queued', ['status' => 'processing'])))
        ->assertOk()
        ->assertExactJson(['received' => true]);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Processing)
        ->and(WebhookEvent::query()->sole()->type)->toBe('generation.processing');
});

it('records a succeeded generation at exactly its cost plus 25%', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded')))->assertOk();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->cost_nanos)->toBe(11_685_000)          // $0.011685, what Pixelforge bills
        ->and($generation->charge_nanos)->toBe(14_606_250)        // $0.01460625 with the markup
        ->and($generation->usage)->toEqual(['input_text_tokens' => 12, 'input_image_tokens' => 0, 'output_tokens' => 280, 'reasoning_tokens' => 34])
        ->and($generation->image_url)->toStartWith('https://pixelforge.promptedit.dev/images/' . TEST_GENERATION_ID)
        ->and($generation->image_url_expires_at?->toIso8601ZuluString())->toBe('2026-11-18T16:49:08Z')
        ->and($generation->completed_at?->toIso8601ZuluString())->toBe('2026-11-17T16:49:08Z');
});

it('charges with the rates locked on the generation, not the ones in the event', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded', [
        'rates' => ['input_text_tokens' => '99.00', 'input_image_tokens' => '99.00', 'output_tokens' => '99.00', 'reasoning_tokens' => '99.00'],
    ])))->assertOk();

    expect($generation->refresh()->charge_nanos)->toBe(14_606_250);
});

it('records a failed generation for free', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.failed', pixelforgeJson('generation-queued', [
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'No image could be made for this prompt.'],
        'completed_at' => '2026-11-17T09:59:30Z',
    ])))->assertOk();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charge_nanos)->toBe(0)
        ->and($generation->cost_nanos)->toBe(0)
        ->and($generation->error_code)->toBe('generation_failed')
        ->and($generation->error_message)->toBe('No image could be made for this prompt.');
});

it('applies an event delivered twice only once', function (): void {
    $generation = acceptedGeneration();
    $event = pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded'));

    deliver($event)->assertOk();
    $firstUpdate = $generation->refresh()->updated_at;
    $this->travel(5)->seconds();
    deliver($event)->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and($generation->refresh()->charge_nanos)->toBe(14_606_250)
        ->and($generation->updated_at?->equalTo($firstUpdate))->toBeTrue();
});

it('never charges twice, even for the same result under two event ids', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded'), id: 'evt_01'))->assertOk();
    $this->travel(5)->seconds();
    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded', [
        'usage' => ['input_text_tokens' => 999, 'input_image_tokens' => 0, 'output_tokens' => 280, 'reasoning_tokens' => 34],
    ]), id: 'evt_02'))->assertOk();

    expect($generation->refresh()->charge_nanos)->toBe(14_606_250)
        ->and(WebhookEvent::query()->count())->toBe(2);
});

it('ignores events that arrive after the result', function (string $late): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded'), id: 'evt_01'))->assertOk();
    deliver(pixelforgeEvent("generation.{$late}", pixelforgeJson('generation-queued', ['status' => $late]), id: 'evt_02'))->assertOk();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nanos)->toBe(14_606_250);
})->with(['processing', 'failed']);

it('never moves a failed generation to succeeded', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.failed', pixelforgeJson('generation-queued', ['status' => 'failed']), id: 'evt_01'))->assertOk();
    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded'), id: 'evt_02'))->assertOk();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charge_nanos)->toBe(0);
});

it('finds a generation by our id when the webhook beats the create call', function (): void {
    $generation = Generation::factory()->create();     // still pending: no pixelforge_id or rates yet

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded', [
        'metadata' => ['generation_id' => $generation->id],
    ])))->assertOk();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->pixelforge_id)->toBe(TEST_GENERATION_ID)
        ->and($generation->rates)->toEqual(pixelforgeJson('generation-succeeded')['rates'])
        ->and($generation->charge_nanos)->toBe(14_606_250);
});

it('stores events for generations that are not ours, and changes nothing', function (): void {
    $generation = acceptedGeneration();

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded', ['id' => 'gen_someone_else'])))->assertOk();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and(WebhookEvent::query()->count())->toBe(1);
});

it('acknowledges a ping from the dashboard', function (): void {
    deliver(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-17T09:59:00Z', 'data' => []])
        ->assertOk();

    expect(WebhookEvent::query()->sole()->type)->toBe('ping');
});

it('rejects requests not signed by Pixelforge', function (Closure $send): void {
    $generation = acceptedGeneration();

    $send(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded')))->assertBadRequest();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and(WebhookEvent::query()->count())->toBe(0);
})->with([
    'wrong secret' => fn (array $event): TestResponse => deliver($event, secret: 'whsec_wrong'),
    'older than 5 minutes' => fn (array $event): TestResponse => deliver($event, timestamp: now()->getTimestamp() - 301),
    'from the future' => fn (array $event): TestResponse => deliver($event, timestamp: now()->getTimestamp() + 301),
    'body changed after signing' => fn (array $event): TestResponse => deliver($event, signedBody: '{"id":"evt_other"}'),
    'no signature' => fn (array $event): TestResponse => test()->postJson(route('webhooks.pixelforge'), $event),
    'garbled signature' => fn (array $event): TestResponse => test()->postJson(route('webhooks.pixelforge'), $event, ['Pixelforge-Signature' => 'nonsense']),
]);

it('accepts a timestamp just inside 5 minutes', function (): void {
    deliver(pixelforgeEvent('ping'), timestamp: now()->getTimestamp() - 300)->assertOk();
});

it('rejects a signed body that is not an event', function (string $body): void {
    deliver($body)->assertBadRequest();

    expect(WebhookEvent::query()->count())->toBe(0);
})->with([
    'not JSON' => ['hello'],
    'no id' => ['{"type":"ping"}'],
    'no type' => ['{"id":"evt_01"}'],
]);

it('answers 500 without the secret, so Pixelforge retries once it is set', function (): void {
    config(['services.pixelforge.webhook_secret' => '']);

    deliver(pixelforgeEvent('ping'))->assertInternalServerError();
});

it('stores nothing when applying fails, so the redelivery is applied', function (): void {
    $generation = acceptedGeneration();
    $broken = pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded', ['usage' => null]));

    $this->withoutExceptionHandling();
    expect(fn (): TestResponse => deliver($broken))->toThrow(PixelforgeException::class);
    expect(WebhookEvent::query()->count())->toBe(0)
        ->and($generation->refresh()->status)->toBe(GenerationStatus::Queued);

    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded')))->assertOk();

    expect($generation->refresh()->charge_nanos)->toBe(14_606_250);
});

it('takes the charge from the user\'s credit once, for a duplicate webhook and a poll', function (): void {
    $user = User::factory()->withCredits()->create();
    $generation = Generation::factory()->for($user)->queued()->create(['pixelforge_id' => TEST_GENERATION_ID]);
    $event = pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded'));

    deliver($event)->assertOk();
    deliver(pixelforgeEvent('generation.succeeded', pixelforgeJson('generation-succeeded'), id: 'evt_02'))->assertOk();
    app(ApplyGenerationUpdate::class)->handle(PixelforgeGeneration::fromResponse(pixelforgeJson('generation-succeeded')));

    expect($user->creditTransactions()->where('type', CreditTransactionType::Charge)->sole()->amount_nanos)->toBe(-14_606_250)
        ->and($user->refresh()->balance_nanos)->toBe(10_000_000_000 - 14_606_250)
        ->and($generation->refresh()->charge_nanos)->toBe(14_606_250);
});

it('takes nothing from the user\'s credit when a generation fails', function (): void {
    $user = User::factory()->withCredits()->create();
    Generation::factory()->for($user)->queued()->create(['pixelforge_id' => TEST_GENERATION_ID]);

    deliver(pixelforgeEvent('generation.failed', pixelforgeJson('generation-queued', [
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'No image could be made for this prompt.'],
    ])))->assertOk();

    expect($user->creditTransactions()->where('type', CreditTransactionType::Charge)->count())->toBe(0)
        ->and($user->refresh()->balance_nanos)->toBe(10_000_000_000);
});
