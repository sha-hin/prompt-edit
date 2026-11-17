<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\PresentsSpend;
use App\Models\CreditTransaction;
use App\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ledger entry with the balance right after it (balance_after, from UserReportController's window query).
 *
 * @property CreditTransaction $resource
 */
final class CreditTransactionResource extends JsonResource
{
    use PresentsSpend;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'type' => $entry->type->value,
            'amount' => $this->money(Money::nanos($entry->amount_nanos)),
            'balance_after' => $this->money(Money::nanos((int) $entry->getAttribute('balance_after'))),
            'generation_id' => $entry->generation_id,
            'created_at' => $entry->created_at?->toIso8601ZuluString(),
        ];
    }
}
