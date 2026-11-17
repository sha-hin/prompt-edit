<?php

namespace App\Http\Resources;

use App\Pricing\CostEstimate;
use App\Pricing\TokenUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the user will pay, as a range. Pixelforge's cost and our markup stay server-side.
 *
 * @property CostEstimate $resource
 */
final class CostEstimateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $min = $this->resource->min->charge;
        $max = $this->resource->max->charge;

        return [
            'charge' => [
                'min' => $min->toDecimal(),                  // exact, 9 decimals, as strings: never floats
                'max' => $max->toDecimal(),
                'display' => $min->equals($max) ? $min->format() : "{$min->format()} – {$max->format()}",
                'max_display' => $max->format(),            // what the user must have available
            ],
            'tokens' => [
                'min' => $this->tokens($this->resource->minUsage),
                'max' => $this->tokens($this->resource->maxUsage),
            ],
        ];
    }

    /**
     * @return array{input_text: int, input_image: int, output: int, reasoning: int, total: int}
     */
    private function tokens(TokenUsage $usage): array
    {
        return [
            'input_text' => $usage->inputText,
            'input_image' => $usage->inputImage,
            'output' => $usage->output,
            'reasoning' => $usage->reasoning,
            'total' => $usage->total(),
        ];
    }
}
