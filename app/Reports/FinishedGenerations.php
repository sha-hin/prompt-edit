<?php

namespace App\Reports;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one query every admin report sums money with, grouped the way Pixelforge bills:
 * by the UTC day a generation completed, not the day it was created.
 */
final class FinishedGenerations
{
    /**
     * Generations with a final charge that completed in the range, summed in integer nanos.
     * Submissions that never reached Pixelforge have no charge, so they are left out.
     *
     * @return Builder<Generation>
     */
    public static function between(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Generation::query()
            ->whereNotNull('charge_nanos')
            ->where('completed_at', '>=', $start->startOfDay())
            ->where('completed_at', '<', $end->startOfDay()->addDay())
            ->selectRaw(
                'SUM(status = ?) as succeeded, SUM(status = ?) as failed, SUM(charge_nanos) as spend, SUM(cost_nanos) as cost',
                [GenerationStatus::Succeeded->value, GenerationStatus::Failed->value],
            );
    }

    /**
     * One row per day, newest first, including empty days so they line up with Pixelforge's.
     *
     * @param  Builder<Generation>  $query  from between()
     * @return array<string, SpendRow> keyed by Y-m-d
     */
    public static function byDay(Builder $query, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = $query->selectRaw('DATE(completed_at) as day')->groupBy('day')->toBase()->get()->keyBy('day');
        $days = [];

        for ($day = $end->startOfDay(); $day->gte($start->startOfDay()); $day = $day->subDay()) {
            $row = $rows->get($day->toDateString());
            $days[$day->toDateString()] = $row === null ? SpendRow::empty() : SpendRow::fromAggregate($row);
        }

        return $days;
    }

    /**
     * @param  Builder<Generation>  $query  from between()
     */
    public static function total(Builder $query): SpendRow
    {
        return SpendRow::fromAggregate($query->toBase()->first() ?? (object) []);
    }
}
