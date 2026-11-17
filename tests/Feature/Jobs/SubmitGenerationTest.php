<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function submit(Generation $generation): SubmitGeneration
{
    $job = new SubmitGeneration($generation->fresh() ?? $generation)->withFakeQueueInteractions();
    $job->handle(app(PixelforgeClient::class));

    return $job;
}

it('stores Pixelforge\'s id and the rates it locked, and marks the generation queued', function (): void {
    $lockedRates = [
        'input_text_tokens' => '6.00',                        // differs from the published rates on purpose
        'input_image_tokens' => '11.00',
        'output_tokens' => '41.00',
        'reasoning_tokens' => '13.00',
    ];
    Http::fake(['*/generations' => Http::response(pixelforgeJson('generation-queued', ['rates' => $lockedRates]), 202)]);
    $generation = Generation::factory()->create();

    $job = submit($generation);

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_id)->toBe('gen_01M7N6J8PJWNEKE6XW84S74ZV9')
        ->and($generation->rates)->toEqual($lockedRates);         // same values; MySQL JSON may reorder the keys
    $job->assertNotReleased();
    $job->assertNotFailed();
});

it('sends the stored idempotency key and our id as metadata', function (): void {
    Http::fake(['*/generations' => Http::response(pixelforgeJson('generation-queued'), 202)]);
    $generation = Generation::factory()->create();

    submit($generation);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', $generation->idempotency_key)
        && $request['metadata'] === ['generation_id' => $generation->id]
        && $request['prompt'] === $generation->prompt);
});

it('waits as long as Pixelforge asks when it is busy', function (int $status, string $code, string $retryAfter, int $delay): void {
    Http::fake(['*/generations' => Http::response(pixelforgeError($code), $status, ['Retry-After' => $retryAfter])]);
    $generation = Generation::factory()->create();

    $job = submit($generation);

    $job->assertReleased(delay: $delay);
    expect($generation->refresh()->status)->toBe(GenerationStatus::Pending);
})->with([
    'concurrency limit' => [429, 'concurrency_limit_exceeded', '5', 5],
    'per-minute limit' => [429, 'rate_limit_exceeded', '30', 30],
    'overloaded' => [503, 'overloaded', '2', 2],
]);

it('fails for free without retrying when Pixelforge rejects the request', function (int $status, string $code): void {
    Http::fake(['*/generations' => Http::response(pixelforgeError($code, 'Pixelforge said no.'), $status)]);
    $generation = Generation::factory()->create();

    $job = submit($generation);

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe($code)
        ->and($generation->error_message)->toBe('Pixelforge said no.')
        ->and($generation->pixelforge_id)->toBeNull();
    $job->assertNotReleased();
})->with([
    'blocked prompt' => [400, 'content_policy_violation'],
    'daily limit' => [429, 'daily_limit_exceeded'],
    'validation failed' => [422, 'validation_failed'],
]);

it('lets a connection error fail the attempt so the queue retries it', function (): void {
    Http::fake(['*/generations' => Http::failedConnection()]);
    $generation = Generation::factory()->create();

    expect(fn (): SubmitGeneration => submit($generation))->toThrow(ConnectionException::class)
        ->and($generation->refresh()->status)->toBe(GenerationStatus::Pending);
});

it('does nothing for a generation that is no longer pending', function (): void {
    Http::fake();
    $generation = Generation::factory()->queued()->create();

    submit($generation);

    Http::assertNothingSent();
});

it('never overwrites a webhook that arrived before the create returned', function (): void {
    $generation = Generation::factory()->create();
    Http::fake(['*/generations' => function () use ($generation) {
        Generation::query()->whereKey($generation->id)->update(['status' => GenerationStatus::Processing]);

        return Http::response(pixelforgeJson('generation-queued'), 202);
    }]);

    submit($generation);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Processing);
});

it('fails for free once it gives up', function (): void {
    $generation = Generation::factory()->create();

    new SubmitGeneration($generation)->failed(new RuntimeException('Timed out.'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_code)->toBe('submission_failed')
        ->and($generation->error_message)->toBe('Pixelforge is busy right now. Please try again.');
});

it('leaves an accepted generation alone when it gives up', function (): void {
    $generation = Generation::factory()->queued()->create();

    new SubmitGeneration($generation)->failed(new RuntimeException('Timed out.'));

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
});

it('runs on the pixelforge queue after commit, retrying for up to 10 minutes', function (): void {
    $job = new SubmitGeneration(Generation::factory()->create());

    expect($job)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($job->queue)->toBe('pixelforge')
        ->and($job->backoff())->toBe([5, 15, 30])
        ->and($job->retryUntil()->getTimestamp())->toBe(now()->addMinutes(10)->getTimestamp());
});
