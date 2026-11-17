<?php

namespace App\Reports;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PixelforgeUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;

/**
 * Builds the admin report from our own records, grouped the way Pixelforge bills:
 * by the UTC day a generation completed, not the day it was created.
 */
final readonly class BuildSpendReport
{
    public function __construct(private PixelforgeClient $pixelforge) {}

    public function __invoke(CarbonImmutable $start, CarbonImmutable $end): SpendReport
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();

        $byDay = $this->finished($start, $end)
            ->selectRaw('DATE(completed_at) as day')
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy('day');

        $days = [];

        for ($day = $end; $day->gte($start); $day = $day->subDay()) {          // every day, so empty ones line up with Pixelforge's
            $row = $byDay->get($day->toDateString());
            $days[$day->toDateString()] = $row === null ? SpendRow::empty() : SpendRow::fromAggregate($row);
        }

        $byUser = $this->finished($start, $end)
            ->addSelect('user_id')
            ->groupBy('user_id')
            ->orderByDesc('spend')
            ->toBase()
            ->get();

        $people = User::query()->findMany($byUser->pluck('user_id'))->keyBy('id');
        $users = [];

        foreach ($byUser as $row) {
            $users[] = [
                'user' => $people->get($row->user_id),                  // null for a deleted account
                'row' => SpendRow::fromAggregate($row),
            ];
        }

        return new SpendReport(
            start: $start,
            end: $end,
            days: $days,
            users: $users,
            total: SpendRow::fromAggregate($this->finished($start, $end)->toBase()->first() ?? (object) []),
            pixelforge: $this->pixelforgeUsage($start, $end),
        );
    }

    /**
     * Generations with a final charge that completed in the range, summed in integer nanos.
     * Submissions that never reached Pixelforge have no charge, so they are left out.
     *
     * @return Builder<Generation>
     */
    private function finished(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Generation::query()
            ->whereNotNull('charge_nanos')
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end->addDay())
            ->selectRaw(
                'SUM(status = ?) as succeeded, SUM(status = ?) as failed, SUM(charge_nanos) as spend, SUM(cost_nanos) as cost',
                [GenerationStatus::Succeeded->value, GenerationStatus::Failed->value],
            );
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
