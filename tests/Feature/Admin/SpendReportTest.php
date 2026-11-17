<?php

use App\Credits\CreditLedger;
use App\Enums\CreditPack;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A generation that finished at $completedAt with Pixelforge's cost and our charge, in nanos.
 */
function finishedGeneration(User $user, string $completedAt, int $cost, int $charge, GenerationStatus $status = GenerationStatus::Succeeded): Generation
{
    return Generation::factory()->for($user)->queued()->create([
        'status' => $status,
        'cost_nanos' => $cost,
        'charge_nanos' => $charge,
        'created_at' => CarbonImmutable::parse($completedAt)->subMinute(),
        'completed_at' => $completedAt,
    ]);
}

/**
 * GET /v1/usage answering with these days (Y-m-d => [generations, cost_usd]); every other day is empty.
 *
 * @param  array<string, array{int, string}>  $days
 */
function fakeUsage(array $days = []): void
{
    Http::fake(['*/usage*' => function (Request $request) use ($days) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $data = [];
        $total = [0, 0];

        for ($day = CarbonImmutable::parse($query['start_date']); $day->lte(CarbonImmutable::parse($query['end_date'])); $day = $day->addDay()) {
            [$generations, $cost] = $days[$day->toDateString()] ?? [0, '0.000000000'];
            $data[] = ['date' => $day->toDateString(), 'generations' => $generations, 'cost_usd' => $cost];
            $total = [$total[0] + $generations, $total[1] + (int) str_replace('.', '', $cost)];
        }

        return Http::response(['object' => 'usage', 'data' => $data, 'total' => [
            'generations' => $total[0],
            'cost_usd' => sprintf('%d.%09d', intdiv($total[1], 1_000_000_000), $total[1] % 1_000_000_000),
        ]]);
    }]);
}

function visitReport(array $query = []): TestResponse
{
    return test()->actingAs(User::factory()->admin()->create())->get(route('admin.report', $query));
}

it('shows operators the last 30 days by default', function (): void {
    fakeUsage();

    visitReport()
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/Report')
            ->where('report.start', '2026-10-19')
            ->where('report.end', '2026-11-17')
            ->has('report.days', 30)
            ->where('report.days.0.date', '2026-11-17')          // newest first
            ->where('report.days.29.date', '2026-10-19')
            ->where('report.pixelforge_available', true));
});

it('is forbidden to users who are not operators', function (): void {
    $this->actingAs(User::factory()->create())->get(route('admin.report'))->assertForbidden();
});

it('sends guests to the login page', function (): void {
    $this->get(route('admin.report'))->assertRedirect(route('login'));
});

it('sums spend, cost and profit per day and in total, to the nano', function (): void {
    $ada = User::factory()->create(['name' => 'Ada']);
    $bob = User::factory()->create(['name' => 'Bob']);
    finishedGeneration($ada, '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);
    finishedGeneration($ada, '2026-11-17 09:30:00', cost: 1, charge: 1);               // 1 nano: rounding must not hide it
    finishedGeneration($bob, '2026-11-16 12:00:00', cost: 47_950_000, charge: 59_937_500);
    finishedGeneration($bob, '2026-11-16 13:00:00', cost: 0, charge: 0, status: GenerationStatus::Failed);
    fakeUsage();

    visitReport(['start' => '2026-11-16', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.days.0.date', '2026-11-17')
            ->where('report.days.0.succeeded', 2)
            ->where('report.days.0.failed', 0)
            ->where('report.days.0.spend.amount', '0.014606251')
            ->where('report.days.0.cost.amount', '0.011685001')
            ->where('report.days.0.profit.amount', '0.002921250')
            ->where('report.days.1.succeeded', 1)
            ->where('report.days.1.failed', 1)
            ->where('report.days.1.profit.amount', '0.011987500')
            ->where('report.total.succeeded', 3)
            ->where('report.total.spend.amount', '0.074543751')
            ->where('report.total.cost.amount', '0.059635001')
            ->where('report.total.profit.amount', '0.014908750')
            ->where('report.total.margin', '20.0%'));
});

it('counts a generation on the UTC day it completed, as Pixelforge bills it', function (): void {
    $generation = finishedGeneration(User::factory()->create(), '2026-11-17 00:01:00', cost: 11_685_000, charge: 14_606_250);
    $generation->forceFill(['created_at' => '2026-11-16 23:59:00'])->save();
    fakeUsage(['2026-11-17' => [1, '0.011685000']]);

    visitReport(['start' => '2026-11-16', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.days.0.date', '2026-11-17')
            ->where('report.days.0.succeeded', 1)
            ->where('report.days.0.pixelforge.matches', true)
            ->where('report.days.1.succeeded', 0)
            ->where('report.days.1.pixelforge.matches', true));
});

it('leaves out generations still running, outside the range, or never submitted', function (): void {
    $user = User::factory()->create();
    Generation::factory()->for($user)->queued()->create();
    Generation::factory()->for($user)->create(['status' => GenerationStatus::Failed, 'error_code' => 'submission_failed']);
    finishedGeneration($user, '2026-11-15 23:59:59', cost: 11_685_000, charge: 14_606_250);
    fakeUsage();

    visitReport(['start' => '2026-11-16', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.total.succeeded', 0)
            ->where('report.total.failed', 0)
            ->where('report.total.spend.amount', '0.000000000'));
});

it('matches Pixelforge when the costs and counts agree', function (): void {
    finishedGeneration(User::factory()->create(), '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);
    fakeUsage(['2026-11-17' => [1, '0.011685000']]);

    visitReport(['start' => '2026-11-17', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.days.0.pixelforge', [
                'succeeded' => 1,
                'cost' => ['amount' => '0.011685000', 'display' => '$0.0117', 'exact' => '$0.011685000'],
                'difference' => ['amount' => '0.000000000', 'display' => '$0.0000', 'exact' => '$0.000000000'],
                'matches' => true,
            ])
            ->where('report.total.pixelforge.matches', true));
});

it('flags a day that is off by a single nano or a single generation', function (array $theirs): void {
    finishedGeneration(User::factory()->create(), '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);
    fakeUsage(['2026-11-17' => $theirs]);

    visitReport(['start' => '2026-11-17', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.days.0.pixelforge.matches', false)
            ->where('report.total.pixelforge.matches', false));
})->with([
    'one nano more' => [[1, '0.011685001']],
    'one more generation, same cost' => [[2, '0.011685000']],
    'a generation we have no record of' => [[2, '0.023370000']],
]);

it('shows the difference when Pixelforge billed something we have no record of', function (): void {
    fakeUsage(['2026-11-17' => [1, '0.011685000']]);

    visitReport(['start' => '2026-11-17', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.days.0.pixelforge.difference.amount', '-0.011685000')
            ->where('report.days.0.pixelforge.matches', false));
});

it('still shows our numbers when Pixelforge is down', function (): void {
    Exceptions::fake();
    Http::fake(['*/usage*' => Http::response(pixelforgeError('overloaded'), 503)]);
    finishedGeneration(User::factory()->create(), '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);

    visitReport(['start' => '2026-11-17', 'end' => '2026-11-17'])
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.pixelforge_available', false)
            ->where('report.days.0.pixelforge', null)
            ->where('report.total.pixelforge', null)
            ->where('report.total.spend.amount', '0.014606250'));

    Exceptions::assertReportedCount(1);
});

it('keeps a deleted user\'s generations in the books', function (): void {
    $user = User::factory()->create();
    finishedGeneration($user, '2026-11-17 09:00:00', cost: 11_685_000, charge: 14_606_250);
    $user->delete();
    fakeUsage(['2026-11-17' => [1, '0.011685000']]);

    visitReport(['start' => '2026-11-17', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.total.spend.amount', '0.014606250')
            ->where('report.total.pixelforge.matches', true));
});

it('shows the credit sold and given away in the range', function (): void {
    $user = User::factory()->create();
    app(CreditLedger::class)->grantSignupBonus($user);
    app(CreditLedger::class)->purchase($user, CreditPack::Ten, 'buy-1');
    app(CreditLedger::class)->purchase($user, CreditPack::Five, 'buy-2');
    $this->travel(-3)->days();
    app(CreditLedger::class)->purchase($user, CreditPack::TwentyFive, 'before-the-range');
    $this->travelBack();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 10:00:00'));
    fakeUsage();

    visitReport(['start' => '2026-11-16', 'end' => '2026-11-17'])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('report.credits_sold.amount', '15.000000000')
            ->where('report.credits_granted.amount', '10.000000000'));
});

it('accepts up to 92 days', function (): void {
    fakeUsage();

    visitReport(['start' => '2026-08-18', 'end' => '2026-11-17'])
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('report.days', 92));
});

it('rejects ranges Pixelforge would refuse, before calling it', function (array $query, string $field): void {
    Http::fake();

    visitReport($query)->assertSessionHasErrors($field);

    Http::assertNothingSent();
})->with([
    '93 days' => [['start' => '2026-08-17', 'end' => '2026-11-17'], 'start'],
    'end before start' => [['start' => '2026-11-17', 'end' => '2026-11-16'], 'end'],
    'in the future' => [['start' => '2026-11-17', 'end' => '2026-11-18'], 'end'],
    'not a date' => [['start' => 'last week'], 'start'],
]);
