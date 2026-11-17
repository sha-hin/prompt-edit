<?php

use App\Credits\CreditLedger;
use App\Credits\InsufficientCredits;
use App\Enums\CreditPack;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Money\Money;

function ledger(): CreditLedger
{
    return app(CreditLedger::class);
}

/**
 * A finished, succeeded generation with $charge nanos to pay.
 */
function succeededGeneration(User $user, int $charge): Generation
{
    return Generation::factory()->for($user)->queued()->create([
        'status' => GenerationStatus::Succeeded,
        'cost_nanos' => intdiv($charge * 4, 5),
        'charge_nanos' => $charge,
    ]);
}

it('gives every new account $10 to try the app', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->sole();
    $bonus = $user->creditTransactions()->sole();
    expect($bonus->type)->toBe(CreditTransactionType::Grant)
        ->and($bonus->amount_nanos)->toBe(10_000_000_000)
        ->and($user->balance_nanos)->toBe(10_000_000_000);
});

it('grants the signup bonus only once', function (): void {
    $user = User::factory()->create();

    ledger()->grantSignupBonus($user);
    ledger()->grantSignupBonus($user);

    expect($user->creditTransactions()->count())->toBe(1)
        ->and($user->refresh()->balance_nanos)->toBe(10_000_000_000);
});

it('charges a succeeded generation once, however often it is applied', function (): void {
    $user = User::factory()->withCredits()->create();
    $generation = succeededGeneration($user, 14_606_250);

    ledger()->charge($generation);
    ledger()->charge($generation);

    $charge = $user->creditTransactions()->where('type', CreditTransactionType::Charge)->sole();
    expect($charge->amount_nanos)->toBe(-14_606_250)
        ->and($charge->generation_id)->toBe($generation->id)
        ->and($user->refresh()->balance_nanos)->toBe(10_000_000_000 - 14_606_250);
});

it('never refuses a charge: the image exists, so the balance can go below zero', function (): void {
    $user = User::factory()->create();

    ledger()->charge(succeededGeneration($user, 14_606_250));

    expect($user->refresh()->balance_nanos)->toBe(-14_606_250);
});

it('keeps a running generation\'s maximum reserved until it finishes', function (): void {
    $user = User::factory()->withCredits(1)->create();
    $running = Generation::factory()->for($user)->queued()->create(['estimate_max_nanos' => 86_275_000]);
    Generation::factory()->for($user)->create(['estimate_max_nanos' => 50_000_000]);   // pending
    Generation::factory()->for($user)->create(['status' => GenerationStatus::Failed, 'estimate_max_nanos' => 1]);
    Generation::factory()->create(['estimate_max_nanos' => 1]);                         // someone else's

    expect(ledger()->reserved($user)->nanos)->toBe(136_275_000)
        ->and(ledger()->available($user)->nanos)->toBe(1_000_000_000 - 136_275_000);

    $running->forceFill(['status' => GenerationStatus::Succeeded])->save();

    expect(ledger()->reserved($user)->nanos)->toBe(50_000_000);
});

it('keeps the balance equal to the sum of the ledger', function (): void {
    $user = User::factory()->create();

    ledger()->grantSignupBonus($user);
    ledger()->purchase($user, CreditPack::Five, 'buy-1');
    ledger()->purchase($user, CreditPack::Five, 'buy-1');               // a repeat
    ledger()->purchase($user, CreditPack::TwentyFive, 'buy-2');
    ledger()->charge(succeededGeneration($user, 14_606_250));
    ledger()->charge(succeededGeneration($user, 1));

    expect($user->refresh()->balance_nanos)
        ->toBe((int) $user->creditTransactions()->sum('amount_nanos'))
        ->toBe(40_000_000_000 - 14_606_251);
});

it('skips the charge for an account that was deleted', function (): void {
    $user = User::factory()->create();
    $generation = succeededGeneration($user, 14_606_250);
    $user->delete();

    ledger()->charge($generation->refresh());

    expect(CreditTransaction::query()->count())->toBe(0);
});

it('says how much is needed and available when credit runs short', function (): void {
    $user = User::factory()->withCredits(1)->create();

    ledger()->lockAndEnsureCanAfford($user, Money::nanos(1_000_000_001));
})->throws(InsufficientCredits::class, 'This image can cost up to $1.0000, and you have $1.0000 available.');
