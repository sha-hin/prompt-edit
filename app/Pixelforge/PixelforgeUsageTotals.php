<?php

namespace App\Pixelforge;

use App\Money\Money;

/**
 * What Pixelforge billed for one day, or for a whole range: succeeded generations and their cost.
 */
final readonly class PixelforgeUsageTotals
{
    public function __construct(
        public int $generations,
        public Money $cost,
    ) {}

    /**
     * @param  array<mixed>  $json
     */
    public static function fromResponse(array $json): self
    {
        if (! is_int($json['generations'] ?? null) || ! is_string($json['cost_usd'] ?? null)) {
            throw PixelforgeException::malformedResponse('a usage entry without generations or cost_usd');
        }

        return new self($json['generations'], Money::fromDecimal($json['cost_usd']));
    }
}
