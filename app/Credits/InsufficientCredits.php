<?php

namespace App\Credits;

use App\Money\Money;
use RuntimeException;

/**
 * A generation could cost more than the user has available.
 */
final class InsufficientCredits extends RuntimeException
{
    public function __construct(
        public readonly Money $needed,
        public readonly Money $available,
    ) {
        parent::__construct("This image can cost up to {$needed->format()}, and you have {$available->format()} available.");
    }
}
