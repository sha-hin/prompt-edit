<?php

namespace App\Http\Resources;

use App\Money\Money;
use App\Pixelforge\PixelforgeUsageTotals;
use App\Reports\SpendReport;
use App\Reports\SpendRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin report for the page: exact amounts as 9-decimal strings plus display text, and
 * Pixelforge's figures beside ours for every day and the total.
 *
 * @property SpendReport $resource
 */
final class SpendReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;
        $usage = $report->pixelforge;

        $days = [];

        foreach ($report->days as $date => $row) {
            $days[] = ['date' => $date, ...$this->row($row), 'pixelforge' => $usage === null ? null : $this->compare($row, $usage->days[$date] ?? null)];
        }

        return [
            'start' => $report->start->toDateString(),
            'end' => $report->end->toDateString(),
            'pixelforge_available' => $usage !== null,
            'total' => [
                ...$this->row($report->total),
                'margin' => $this->margin($report->total),
                'pixelforge' => $usage === null ? null : $this->compare($report->total, $usage->total),
            ],
            'days' => $days,
            'users' => array_map(fn (array $entry): array => [
                'id' => $entry['user']?->id,
                'name' => $entry['user']->name ?? 'Deleted user',
                'email' => $entry['user']?->email,
                ...$this->row($entry['row']),
            ], $report->users),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SpendRow $row): array
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
     * Ours against Pixelforge's. A day Pixelforge left out counts as zero, so any activity of ours there is a mismatch.
     *
     * @return array<string, mixed>
     */
    private function compare(SpendRow $ours, ?PixelforgeUsageTotals $theirs): array
    {
        $theirs ??= new PixelforgeUsageTotals(0, Money::zero());
        $difference = $ours->cost->subtract($theirs->cost);

        return [
            'succeeded' => $theirs->generations,
            'cost' => $this->money($theirs->cost),
            'difference' => $this->money($difference),
            'matches' => $difference->nanos === 0 && $ours->succeeded === $theirs->generations,
        ];
    }

    /**
     * Profit as a share of spend, to one decimal (20.0% at a 25% markup). Null before anything was spent.
     */
    private function margin(SpendRow $row): ?string
    {
        if ($row->spend->nanos === 0) {
            return null;
        }

        $tenthsOfPercent = intdiv($row->profit()->nanos * 1_000 + intdiv($row->spend->nanos, 2), $row->spend->nanos);

        return sprintf('%d.%d%%', intdiv($tenthsOfPercent, 10), abs($tenthsOfPercent % 10));
    }

    /**
     * @return array{amount: string, display: string, exact: string}
     */
    private function money(Money $money): array
    {
        return ['amount' => $money->toDecimal(), 'display' => $money->format(), 'exact' => $money->format(9)];
    }
}
