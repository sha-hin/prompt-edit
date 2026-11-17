<?php

namespace App\Pricing;

use InvalidArgumentException;

final class InvalidRate extends InvalidArgumentException
{
    public static function notExact(string $usdPerMillion): self
    {
        return new self("Rate [{$usdPerMillion}] per 1M tokens is not a whole number of nano-dollars per token.");
    }
}
