<?php

use App\Enums\CreditTransactionType;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

it('adds a pack\'s credit to the balance', function (string $pack, int $nanos): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('generations.index'))
        ->post(route('credits.purchase'), ['pack' => $pack, 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('generations.index'));

    $purchase = $user->creditTransactions()->sole();
    expect($purchase->type)->toBe(CreditTransactionType::Purchase)
        ->and($purchase->amount_nanos)->toBe($nanos)
        ->and($user->refresh()->balance_nanos)->toBe($nanos);
})->with([
    '$5' => ['5', 5_000_000_000],
    '$10' => ['10', 10_000_000_000],
    '$25' => ['25', 25_000_000_000],
]);

it('buys once when the same purchase is sent twice', function (): void {
    $user = User::factory()->create();
    $purchase = ['pack' => '10', 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($user)->post(route('credits.purchase'), $purchase)->assertRedirect();
    $this->actingAs($user)->post(route('credits.purchase'), $purchase)->assertRedirect();

    expect($user->creditTransactions()->count())->toBe(1)
        ->and($user->refresh()->balance_nanos)->toBe(10_000_000_000);
});

it('lets two users use the same key', function (): void {
    $key = (string) Str::uuid();
    [$ada, $bob] = User::factory()->count(2)->create();

    $this->actingAs($ada)->post(route('credits.purchase'), ['pack' => '5', 'idempotency_key' => $key]);
    $this->actingAs($bob)->post(route('credits.purchase'), ['pack' => '5', 'idempotency_key' => $key]);

    expect($ada->refresh()->balance_nanos)->toBe(5_000_000_000)
        ->and($bob->refresh()->balance_nanos)->toBe(5_000_000_000);
});

it('only sells the listed packs', function (array $payload, string $field): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('credits.purchase'), $payload)->assertSessionHasErrors($field);

    expect($user->creditTransactions()->count())->toBe(0);
})->with([
    'any amount' => [['pack' => '1000', 'idempotency_key' => '3f1c6a2e-8d1b-4b6e-9a51-6f0f0a1f2b3c'], 'pack'],
    'no pack' => [['idempotency_key' => '3f1c6a2e-8d1b-4b6e-9a51-6f0f0a1f2b3c'], 'pack'],
    'no key' => [['pack' => '5'], 'idempotency_key'],
    'a key that is not a uuid' => [['pack' => '5', 'idempotency_key' => 'again'], 'idempotency_key'],
]);

it('requires a signed-in user', function (): void {
    $this->post(route('credits.purchase'), ['pack' => '5', 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('login'));
});

it('limits purchases to 10 a minute', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 10) as $ignored) {
        $this->actingAs($user)->post(route('credits.purchase'), ['pack' => '5', 'idempotency_key' => (string) Str::uuid()]);
    }

    $this->actingAs($user)
        ->post(route('credits.purchase'), ['pack' => '5', 'idempotency_key' => (string) Str::uuid()])
        ->assertTooManyRequests();
});

it('shares the balance, what is reserved and what is available with every page', function (): void {
    $user = User::factory()->withCredits(5)->create();
    Generation::factory()->for($user)->queued()->create(['estimate_max_nanos' => 86_275_000]);

    $this->actingAs($user)
        ->get(route('generations.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('credits.balance', ['amount' => '5.000000000', 'display' => '$5.00', 'exact' => '$5.000000000'])
            ->where('credits.reserved.amount', '0.086275000')
            ->where('credits.available.amount', '4.913725000'));
});
