<?php

namespace App\Http\Resources\Concerns;

use App\Money\Money;
use App\Reports\SpendRow;

/**
 * How the admin pages send money: the exact 9-decimal amount, 4-decimal display text and the full figure.
 */
trait PresentsSpend
{
    /**
     * @return array{amount: string, display: string, exact: string}
     */
    protected function money(Money $money): array
    {
        return ['amount' => $money->toDecimal(), 'display' => $money->format(), 'exact' => $money->format(9)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function spend(SpendRow $row): array
    {
        return [
            'succeeded' => $row->succeeded,
            'failed' => $row->failed,
            'spend' => $this->money($row->spend),
            'cost' => $this->money($row->cost),
            'profit' => $this->money($row->profit()),
        ];
    }

    /**
     * Profit as a share of spend, to one decimal (20.0% at a 25% markup). Null before anything was spent.
     */
    protected function margin(SpendRow $row): ?string
    {
        if ($row->spend->nanos === 0) {
            return null;
        }

        $tenthsOfPercent = intdiv($row->profit()->nanos * 1_000 + intdiv($row->spend->nanos, 2), $row->spend->nanos);

        return sprintf('%d.%d%%', intdiv($tenthsOfPercent, 10), abs($tenthsOfPercent % 10));
    }
}
