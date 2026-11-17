<?php

namespace App\Reports;

use App\Enums\CreditTransactionType;
use App\Models\User;
use App\Money\Money;
use Carbon\CarbonImmutable;

/**
 * One user's spend for the admin: their days and totals in the range, using the same sums as the overview.
 */
final readonly class BuildUserReport
{
    public function __invoke(User $user, CarbonImmutable $start, CarbonImmutable $end): UserReport
    {
        $query = fn () => FinishedGenerations::between($start, $end)->where('user_id', $user->id);

        return new UserReport(
            user: $user,
            start: $start->startOfDay(),
            end: $end->startOfDay(),
            days: FinishedGenerations::byDay($query(), $start, $end),
            total: FinishedGenerations::total($query()),
            purchased: Money::nanos((int) $user->creditTransactions()->where('type', CreditTransactionType::Purchase)->sum('amount_nanos')),
            granted: Money::nanos((int) $user->creditTransactions()->where('type', CreditTransactionType::Grant)->sum('amount_nanos')),
        );
    }
}
