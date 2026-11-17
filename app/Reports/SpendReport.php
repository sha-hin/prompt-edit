<?php

namespace App\Reports;

use App\Models\User;
use App\Pixelforge\PixelforgeUsage;
use Carbon\CarbonImmutable;

/**
 * Credit spend, provider cost and profit for a range of UTC days, with Pixelforge's own figures beside ours.
 */
final readonly class SpendReport
{
    /**
     * @param  array<string, SpendRow>  $days  keyed by Y-m-d, newest first, with a row for every day in the range
     * @param  list<array{user: User|null, row: SpendRow}>  $users  highest spend first; user is null for deleted accounts
     * @param  PixelforgeUsage|null  $pixelforge  null when Pixelforge couldn't be reached
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $days,
        public array $users,
        public SpendRow $total,
        public ?PixelforgeUsage $pixelforge,
    ) {}
}
