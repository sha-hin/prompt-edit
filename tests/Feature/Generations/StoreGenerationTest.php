<?php

use App\Credits\CreditLedger;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Money\Money;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
});

$cube = [
    'prompt' => 'a red cube',
    'size' => '1536x1024',
    'quality' => 'low',
    'reference_images' => ['https://example.com/a.png'],
];

it('saves a pending generation with its estimate and queues it for Pixelforge', function () use ($cube): void {
    fakePricing();
    $user = User::factory()->withCredits()->create();

    $this->actingAs($user)
        ->post(route('generations.store'), $cube)
        ->assertRedirect(route('generations.index'));

    $generation = $user->generations()->sole();
    expect($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->prompt)->toBe('a red cube')
        ->and($generation->size)->toBe(ImageSize::Landscape)
        ->and($generation->quality)->toBe(ImageQuality::Low)
        ->and($generation->reference_images)->toBe(['https://example.com/a.png'])
        ->and(Str::isUuid($generation->idempotency_key))->toBeTrue()
        ->and($generation->pixelforge_id)->toBeNull()
        ->and($generation->estimate_min_nanos)->toBe(30_575_000)    // 2-4 text, 765 image, 420 output, 0-500 reasoning tokens, +25%
        ->and($generation->estimate_max_nanos)->toBe(38_400_000);

    Queue::assertPushedOn('pixelforge', SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($generation));
});

it('never calls Pixelforge to create during the request', function () use ($cube): void {
    fakePricing();

    $this->actingAs(User::factory()->withCredits()->create())->post(route('generations.store'), $cube);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/pricing'));
});

it('estimates with the live rates', function () use ($cube): void {
    fakePricing(['input_text_tokens' => '10.00', 'input_image_tokens' => '20.00', 'output_tokens' => '80.00', 'reasoning_tokens' => '25.00']);
    $user = User::factory()->withCredits()->create();

    $this->actingAs($user)->post(route('generations.store'), $cube);

    $generation = $user->generations()->sole();
    expect($generation->estimate_min_nanos)->toBe(61_150_000)       // twice the published rates, so twice the estimate
        ->and($generation->estimate_max_nanos)->toBe(76_800_000);
});

it('fills in Pixelforge\'s defaults', function (): void {
    fakePricing();
    $user = User::factory()->withCredits()->create();

    $this->actingAs($user)->post(route('generations.store'), ['prompt' => 'a red cube']);

    $generation = $user->generations()->sole();
    expect($generation->size)->toBe(ImageSize::Square)
        ->and($generation->quality)->toBe(ImageQuality::Medium)
        ->and($generation->reference_images)->toBe([]);
});

it('requires a prompt', function (array $payload): void {
    $user = User::factory()->withCredits()->create();

    $this->actingAs($user)
        ->post(route('generations.store'), $payload)
        ->assertSessionHasErrors('prompt');

    expect($user->generations()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'missing' => [[]],
    'empty' => [['prompt' => '']],
    'too long' => [['prompt' => str_repeat('a', 4_001)]],
]);

it('requires a signed-in, verified user', function (?User $user, string $redirect) use ($cube): void {
    if ($user instanceof User) {
        $this->actingAs($user);
    }

    $this->post(route('generations.store'), $cube)->assertRedirect(route($redirect));

    Queue::assertNothingPushed();
})->with([
    'guest' => [null, 'login'],
    'unverified' => [fn () => User::factory()->unverified()->create(), 'verification.notice'],
]);

/**
 * A user with exactly $nanos of credit.
 */
function userWithCredit(int $nanos): User
{
    $user = User::factory()->create();
    app(CreditLedger::class)->grant($user, Money::nanos($nanos), 'test-credit');

    return $user;
}

it('generates when the credit exactly covers the estimate maximum', function () use ($cube): void {
    fakePricing();
    $user = userWithCredit(38_400_000);                     // the cube's maximum

    $this->actingAs($user)->post(route('generations.store'), $cube)->assertSessionHasNoErrors();

    expect($user->generations()->count())->toBe(1);
});

it('refuses a generation one nano short, and saves and queues nothing', function () use ($cube): void {
    fakePricing();
    $user = userWithCredit(38_399_999);

    $this->actingAs($user)
        ->post(route('generations.store'), $cube)
        ->assertSessionHasErrors('credits');

    expect($user->generations()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('tells the user what the image can cost and what they have', function () use ($cube): void {
    fakePricing();
    $user = userWithCredit(20_000_000);

    $this->actingAs($user)
        ->post(route('generations.store'), $cube)
        ->assertSessionHasErrors(['credits' => 'This image can cost up to $0.0384, and you have $0.0200 available.']);
});

it('counts what running generations may still cost against the credit', function () use ($cube): void {
    fakePricing();
    $user = userWithCredit(76_800_000);                     // two cubes' worth

    $this->actingAs($user)->post(route('generations.store'), $cube)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('generations.store'), $cube)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('generations.store'), $cube)->assertSessionHasErrors('credits');

    expect($user->generations()->count())->toBe(2);
});

it('frees the reservation once a generation fails', function (string $errorCode) use ($cube): void {
    fakePricing();
    $user = userWithCredit(38_400_000);
    Generation::factory()->for($user)->create([
        'status' => GenerationStatus::Failed,
        'estimate_max_nanos' => 38_400_000,
        'charge_nanos' => 0,
        'error_code' => $errorCode,
    ]);

    $this->actingAs($user)->post(route('generations.store'), $cube)->assertSessionHasNoErrors();
})->with(['generation_failed', 'submission_failed', 'not_found']);
