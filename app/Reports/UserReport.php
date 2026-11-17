<?php

namespace App\Reports;

use App\Models\User;
use App\Money\Money;
use Carbon\CarbonImmutable;

final readonly class UserReport
{
    /**
     * @param  array<string, SpendRow>  $days  keyed by Y-m-d, newest first, with a row for every day in the range
     */
    public function __construct(
        public User $user,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $days,
        public SpendRow $total,
        public Money $purchased,            // all time
        public Money $granted,              // all time
    ) {}
}
