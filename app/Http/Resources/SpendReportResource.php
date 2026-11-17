<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsSpend;
use App\Money\Money;
use App\Pixelforge\PixelforgeUsageTotals;
use App\Reports\SpendReport;
use App\Reports\SpendRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin overview for the page, with Pixelforge's figures beside ours for every day and the total.
 *
 * @property SpendReport $resource
 */
final class SpendReportResource extends JsonResource
{
    use PresentsSpend;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;
        $usage = $report->pixelforge;

        $days = [];

        foreach ($report->days as $date => $row) {
            $days[] = ['date' => $date, ...$this->spend($row), 'pixelforge' => $usage === null ? null : $this->compare($row, $usage->days[$date] ?? null)];
        }

        return [
            'start' => $report->start->toDateString(),
            'end' => $report->end->toDateString(),
            'pixelforge_available' => $usage !== null,
            'total' => [
                ...$this->spend($report->total),
                'margin' => $this->margin($report->total),
                'pixelforge' => $usage === null ? null : $this->compare($report->total, $usage->total),
            ],
            'credits_sold' => $this->money($report->creditsSold),
            'credits_granted' => $this->money($report->creditsGranted),
            'days' => $days,
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
}
