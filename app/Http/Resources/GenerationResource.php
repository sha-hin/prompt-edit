<?php

namespace App\Http\Resources;

use App\Models\Generation;
use App\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as its owner sees it. Pixelforge's cost and our markup stay server-side.
 *
 * @property Generation $resource
 */
final class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $generation = $this->resource;
        $min = Money::nanos($generation->estimate_min_nanos);
        $max = Money::nanos($generation->estimate_max_nanos);
        $charge = $generation->charge_nanos === null ? null : Money::nanos($generation->charge_nanos);

        return [
            'id' => $generation->id,
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'reference_images' => $generation->reference_images,
            'status' => $generation->status->value,
            'estimate' => [
                'min' => $min->toDecimal(),
                'max' => $max->toDecimal(),
                'display' => $min->equals($max) ? $min->format() : "{$min->format()} – {$max->format()}",
            ],
            'charge' => $charge === null ? null : [             // null until Pixelforge reports a result
                'amount' => $charge->toDecimal(),
                'display' => $charge->format(),
                'exact' => $charge->format(9),
            ],
            'error_message' => $generation->error_message,
            'image_url' => $generation->image_url,
            'image_url_expires_at' => $generation->image_url_expires_at?->toIso8601ZuluString(),
            'created_at' => $generation->created_at?->toIso8601ZuluString(),
            'completed_at' => $generation->completed_at?->toIso8601ZuluString(),
        ];
    }
}
