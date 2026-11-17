<?php

namespace App\Pricing;

/**
 * The lowest and highest a generation can cost before Pixelforge reports its real usage.
 */
final readonly class CostEstimate
{
    public function __construct(
        public TokenUsage $minUsage,
        public TokenUsage $maxUsage,
        public Price $min,
        public Price $max,
    ) {}
}
