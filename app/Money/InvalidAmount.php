<?php

namespace App\Money;

use InvalidArgumentException;

final class InvalidAmount extends InvalidArgumentException
{
    public static function fromDecimal(string $dollars): self
    {
        return new self("[{$dollars}] is not a dollar amount with at most 9 decimals.");
    }
}
