<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function staleGeneration(string $state = 'pending'): Generation
{
    $factory = Generation::factory();

    if ($state === 'queued') {
        $factory = $factory->queued()->state(['pixelforge_id' => 'gen_01M7N6J8PJWNEKE6XW84S74ZV9']);  // the fixtures' id
    }

    return $factory->create(['updated_at' => now()->subMinutes(3)]);
}

it('resubmits generations that have been pending for more than 2 minutes', function (): void {
    Queue::fake();
    $stuck = staleGeneration();
    $fresh = Generation::factory()->create();

    $this->artisan('generations:sweep')->assertSuccessful();

    Queue::assertPushed(SubmitGeneration::class, 1);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($stuck));
    Queue::assertNotPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($fresh));
});

it('does not queue a second submission while one is still waiting', function (): void {
    Queue::fake();
    $stuck = staleGeneration();
    SubmitGeneration::dispatch($stuck);                     // e.g. released for a Retry-After

    $this->artisan('generations:sweep')->assertSuccessful();

    Queue::assertPushed(SubmitGeneration::class, 1);
});

it('polls a generation whose webhook never came and records the result', function (): void {
    Http::fake(['*/generations/*' => Http::response(pixelforgeJson('generation-succeeded'))]);
    $generation = staleGeneration('queued');

    $this->artisan('generations:sweep')->assertSuccessful();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nanos)->toBe(14_606_250);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pixelforge.test/v1/generations/gen_01M7N6J8PJWNEKE6XW84S74ZV9');
});

it('leaves recently updated and finished generations alone', function (): void {
    Http::fake();
    Generation::factory()->queued()->create();
    Generation::factory()->queued()->create(['status' => GenerationStatus::Succeeded, 'updated_at' => now()->subHour()]);

    $this->artisan('generations:sweep')->assertSuccessful();

    Http::assertNothingSent();
});

it('checks a still-running generation again later, not every minute', function (): void {
    Http::fake(['*/generations/*' => Http::response(pixelforgeJson('generation-queued', ['status' => 'processing']))]);
    $generation = staleGeneration('queued');

    $this->artisan('generations:sweep')->assertSuccessful();
    $this->artisan('generations:sweep')->assertSuccessful();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Processing);
    Http::assertSentCount(1);
});

it('fails a generation for free when Pixelforge no longer has it', function (): void {
    Http::fake(['*/generations/*' => Http::response(pixelforgeError('not_found', 'No such resource.'), 404)]);
    $generation = staleGeneration('queued');

    $this->artisan('generations:sweep')->assertSuccessful();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->charge_nanos)->toBe(0)
        ->and($generation->error_code)->toBe('not_found');
});

it('tries again next minute when Pixelforge is busy or unreachable', function (mixed $response): void {
    Http::fake(['*/generations/*' => $response]);
    $generation = staleGeneration('queued');

    $this->artisan('generations:sweep')->assertSuccessful();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
})->with([
    'overloaded' => fn () => Http::response(pixelforgeError('overloaded'), 503, ['Retry-After' => '2']),
    'rate limited' => fn () => Http::response(pixelforgeError('rate_limit_exceeded'), 429, ['Retry-After' => '10']),
    'unreachable' => fn () => Http::failedConnection(),
]);

it('polls at most 20 generations per run', function (): void {
    Http::fake(['*/generations/*' => Http::response(pixelforgeJson('generation-queued', ['status' => 'processing']))]);
    Generation::factory()->queued()->count(25)->create(['updated_at' => now()->subMinutes(3)]);

    $this->artisan('generations:sweep')->assertSuccessful();

    Http::assertSentCount(20);
});

it('runs every minute', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('generations:sweep')
        ->assertSuccessful();
});
