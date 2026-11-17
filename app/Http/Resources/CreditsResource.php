<?php

namespace App\Http\Resources;

use App\Credits\CreditLedger;
use App\Models\User;
use App\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's credit, shared with every page.
 *
 * @property User $resource
 */
final class CreditsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ledger = app(CreditLedger::class);
        $reserved = $ledger->reserved($this->resource);
        $balance = Money::nanos($this->resource->balance_nanos);

        return [
            'balance' => $this->money($balance),
            'reserved' => $this->money($reserved),              // the most that running generations may still cost
            'available' => $this->money($balance->subtract($reserved)),
        ];
    }

    /**
     * @return array{amount: string, display: string, exact: string}
     */
    private function money(Money $money): array
    {
        return ['amount' => $money->toDecimal(), 'display' => $money->format(2), 'exact' => $money->format(9)];
    }
}
