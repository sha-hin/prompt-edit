<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\PresentsSpend;
use App\Models\User;
use App\Money\Money;
use App\Reports\SpendRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user in the admin users list, with their spend for the chosen range joined on by UserReportController.
 *
 * @property User $resource
 */
final class UserRowResource extends JsonResource
{
    use PresentsSpend;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'joined_at' => $user->created_at?->toIso8601ZuluString(),
            'balance' => $this->money(Money::nanos($user->balance_nanos)),
            ...$this->spend(SpendRow::fromAggregate((object) $user->only(['succeeded', 'failed', 'spend', 'cost']))),
        ];
    }
}
