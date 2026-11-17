<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\PresentsSpend;
use App\Money\Money;
use App\Reports\UserReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property UserReport $resource
 */
final class UserReportResource extends JsonResource
{
    use PresentsSpend;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;
        $user = $report->user;
        $days = [];

        foreach ($report->days as $date => $row) {
            $days[] = ['date' => $date, ...$this->spend($row)];
        }

        return [
            'start' => $report->start->toDateString(),
            'end' => $report->end->toDateString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'joined_at' => $user->created_at?->toIso8601ZuluString(),
                'balance' => $this->money(Money::nanos($user->balance_nanos)),
                'purchased' => $this->money($report->purchased),
                'granted' => $this->money($report->granted),
            ],
            'total' => [...$this->spend($report->total), 'margin' => $this->margin($report->total)],
            'days' => $days,
        ];
    }
}
