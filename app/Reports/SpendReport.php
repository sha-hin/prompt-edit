<?php

namespace App\Reports;

use App\Money\Money;
use App\Pixelforge\PixelforgeUsage;
use Carbon\CarbonImmutable;

/**
 * Credit spend, provider cost and profit for a range of UTC days, with Pixelforge's own figures beside ours.
 */
final readonly class SpendReport
{
    /**
     * @param  array<string, SpendRow>  $days  keyed by Y-m-d, newest first, with a row for every day in the range
     * @param  PixelforgeUsage|null  $pixelforge  null when Pixelforge couldn't be reached
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $days,
        public SpendRow $total,
        public Money $creditsSold,          // purchases made in the range
        public Money $creditsGranted,       // free credit given in the range, such as signup bonuses
        public ?PixelforgeUsage $pixelforge,
    ) {}
}
