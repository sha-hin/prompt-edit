<?php

namespace App\Pricing;

use App\Generations\GenerationSpec;

/**
 * Bounds a generation's token usage from the Pixelforge docs, then prices both ends.
 */
final readonly class GenerationEstimator
{
    public const int TOKENS_PER_REFERENCE_IMAGE = 765;

    public function __construct(private PriceCalculator $calculator) {}

    public function estimate(GenerationSpec $spec, Rates $rates): CostEstimate
    {
        $textTokens = intdiv(mb_strlen($spec->prompt) + 3, 4);         // ceil(chars / 4)
        $imageTokens = count($spec->referenceImages) * self::TOKENS_PER_REFERENCE_IMAGE;
        $outputTokens = $spec->quality->outputTokens($spec->size);

        $min = new TokenUsage(
            intdiv($textTokens * 85, 100),                              // actual text is within -15%, rounded down
            $imageTokens,
            $outputTokens,
            $spec->quality->minReasoningTokens(),
        );

        $max = new TokenUsage(
            intdiv($textTokens * 115 + 99, 100),                        // and +15%, rounded up
            $imageTokens,
            $outputTokens,
            $spec->quality->maxReasoningTokens(),
        );

        return new CostEstimate(
            $min,
            $max,
            $this->calculator->price($min, $rates),
            $this->calculator->price($max, $rates),
        );
    }
}
