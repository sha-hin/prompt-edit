<?php

namespace App\Enums;

use App\Money\Money;

/**
 * What "Buy credits" sells, in whole dollars. The server prices the pack, never the client.
 */
enum CreditPack: string
{
    case Five = '5';
    case Ten = '10';
    case TwentyFive = '25';

    public function money(): Money
    {
        return Money::nanos((int) $this->value * Money::NANOS_PER_DOLLAR);
    }
}
