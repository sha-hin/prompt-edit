<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\PresentsSpend;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as an operator sees it: what the user sees, plus what Pixelforge charged us and our profit.
 *
 * @property Generation $resource
 */
final class AdminGenerationResource extends JsonResource
{
    use PresentsSpend;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generation = $this->resource;
        $finished = $generation->cost_nanos !== null && $generation->charge_nanos !== null;

        return [
            ...GenerationResource::make($generation)->resolve($request),
            'cost' => $finished ? $this->money(Money::nanos((int) $generation->cost_nanos)) : null,
            'profit' => $finished ? $this->money(Money::nanos((int) $generation->charge_nanos - (int) $generation->cost_nanos)) : null,
        ];
    }
}
