<?php

use App\Credits\CreditLedger;
use App\Enums\CreditPack;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

function completed(User $user, string $completedAt, int $cost, int $charge, GenerationStatus $status = GenerationStatus::Succeeded): Generation
{
    return Generation::factory()->for($user)->queued()->create([
        'status' => $status,
        'cost_nanos' => $cost,
        'charge_nanos' => $charge,
        'created_at' => CarbonImmutable::parse($completedAt)->subMinute(),
        'completed_at' => $completedAt,
    ]);
}

function asAdmin(): TestCase
{
    $admin = User::query()->where('email', 'zed@example.com')->first()
        ?? User::factory()->admin()->create(['name' => 'Zed Admin', 'email' => 'zed@example.com']);

    return test()->actingAs($admin);
}

it('lists every user with their spend in the range, highest first, including users with none', function (): void {
    $ada = User::factory()->create(['name' => 'Ada']);
    $bob = User::factory()->create(['name' => 'Bob']);
    User::factory()->create(['name' => 'Cy']);
    completed($ada, '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);
    completed($bob, '2026-11-16 09:00:00', cost: 47_950_000, charge: 59_937_500);
    completed($bob, '2026-11-16 10:00:00', cost: 0, charge: 0, status: GenerationStatus::Failed);
    completed($bob, '2026-11-01 10:00:00', cost: 1_000, charge: 1_250);       // outside the range

    asAdmin()
        ->get(route('admin.users.index', ['start' => '2026-11-16', 'end' => '2026-11-17']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/users/Index')
            ->has('users.data', 4)
            ->where('users.data.0.name', 'Bob')
            ->where('users.data.0.succeeded', 1)
            ->where('users.data.0.failed', 1)
            ->where('users.data.0.spend.amount', '0.059937500')
            ->where('users.data.0.profit.amount', '0.011987500')
            ->where('users.data.1.name', 'Ada')
            ->where('users.data.1.cost.amount', '0.011685000')
            ->where('users.data.2.spend.amount', '0.000000000')
            ->where('users.data.3.spend.amount', '0.000000000')
            ->where('filters.sort', 'spend'));
});

it('searches users by name or email', function (): void {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Bob', 'email' => 'bob@lovelace.dev']);
    User::factory()->create(['name' => 'Cy', 'email' => 'cy@example.com']);

    asAdmin()
        ->get(route('admin.users.index', ['search' => 'lovelace']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('users.data', 2));
});

it('sorts users by balance or by when they joined', function (): void {
    $rich = User::factory()->withCredits(50)->create(['created_at' => now()->subYear()]);
    $new = User::factory()->create();

    asAdmin()
        ->get(route('admin.users.index', ['sort' => 'balance']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('users.data.0.id', $rich->id));

    asAdmin()
        ->get(route('admin.users.index', ['sort' => 'joined']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('users.data.2.id', $rich->id));
});

it('shows one user\'s spend by day, their generations and their ledger', function (): void {
    $ada = User::factory()->create(['name' => 'Ada']);
    $ledger = app(CreditLedger::class);
    $ledger->grantSignupBonus($ada);
    $ledger->purchase($ada, CreditPack::Five, 'buy-1');
    $generation = completed($ada, '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);
    $ledger->charge($generation);
    completed(User::factory()->create(), '2026-11-17 09:00:00', cost: 99, charge: 123);    // someone else's

    asAdmin()
        ->get(route('admin.users.show', ['user' => $ada, 'start' => '2026-11-16', 'end' => '2026-11-17']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/users/Show')
            ->where('report.user.name', 'Ada')
            ->where('report.user.balance.amount', '14.985393750')
            ->where('report.user.purchased.amount', '5.000000000')
            ->where('report.user.granted.amount', '10.000000000')
            ->has('report.days', 2)
            ->where('report.days.0.spend.amount', '0.014606250')
            ->where('report.total.profit.amount', '0.002921250')
            ->where('report.total.margin', '20.0%')
            ->has('generations.data', 1)
            ->where('generations.data.0.cost.amount', '0.011685000')
            ->where('generations.data.0.profit.amount', '0.002921250')
            ->has('ledger.data', 3)
            ->where('ledger.data.0.type', 'charge')
            ->where('ledger.data.0.balance_after.amount', '14.985393750')       // newest first, with the balance after each entry
            ->where('ledger.data.1.type', 'purchase')
            ->where('ledger.data.1.balance_after.amount', '15.000000000')
            ->where('ledger.data.2.balance_after.amount', '10.000000000'));
});

it('keeps the running balance right on later ledger pages', function (): void {
    $ada = User::factory()->create();
    foreach (range(1, 12) as $i) {
        app(CreditLedger::class)->purchase($ada, CreditPack::Five, "buy-{$i}");
    }

    asAdmin()
        ->get(route('admin.users.show', ['user' => $ada, 'ledger_page' => 2]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('ledger.data', 2)
            ->where('ledger.data.0.balance_after.amount', '10.000000000')
            ->where('ledger.data.1.balance_after.amount', '5.000000000'));
});

it('is only for operators', function (string $route): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($route, ['user' => $user]))->assertForbidden();
})->with(['admin.users.index', 'admin.users.show']);
