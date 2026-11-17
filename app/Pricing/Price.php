<?php

namespace App\Pricing;

use App\Money\Money;

/**
 * What a generation costs us (Pixelforge) and what we charge the user for it.
 */
final readonly class Price
{
    public function __construct(
        public Money $cost,
        public Money $markup,
        public Money $charge,
    ) {}
}
