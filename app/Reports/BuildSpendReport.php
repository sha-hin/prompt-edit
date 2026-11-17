<?php

namespace App\Reports;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Money\Money;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PixelforgeUsage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;

/**
 * The admin overview: spend, cost and profit per UTC day, checked against Pixelforge's usage,
 * plus the credit sold and given away over the same days.
 */
final readonly class BuildSpendReport
{
    public function __construct(private PixelforgeClient $pixelforge) {}

    public function __invoke(CarbonImmutable $start, CarbonImmutable $end): SpendReport
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();

        return new SpendReport(
            start: $start,
            end: $end,
            days: FinishedGenerations::byDay(FinishedGenerations::between($start, $end), $start, $end),
            total: FinishedGenerations::total(FinishedGenerations::between($start, $end)),
            creditsSold: $this->credit(CreditTransactionType::Purchase, $start, $end),
            creditsGranted: $this->credit(CreditTransactionType::Grant, $start, $end),
            pixelforge: $this->pixelforgeUsage($start, $end),
        );
    }

    private function credit(CreditTransactionType $type, CarbonImmutable $start, CarbonImmutable $end): Money
    {
        return Money::nanos((int) CreditTransaction::query()
            ->where('type', $type)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end->addDay())
            ->sum('amount_nanos'));
    }

    private function pixelforgeUsage(CarbonImmutable $start, CarbonImmutable $end): ?PixelforgeUsage
    {
        try {
            return $this->pixelforge->usage($start, $end);
        } catch (PixelforgeException|ConnectionException $e) {
            report($e);

            return null;                                    // the page still shows our numbers, without the comparison
        }
    }
}
