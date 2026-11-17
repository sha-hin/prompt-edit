<?php

namespace App\Pricing;

use App\Money\Money;

/**
 * The one place a price is calculated, for the estimate and the actual charge alike.
 */
final readonly class PriceCalculator
{
    public const int MARKUP_BASIS_POINTS = 2_500;           // 25% on top of Pixelforge's cost

    public function price(TokenUsage $usage, Rates $rates): Price
    {
        $cost = Money::nanos(                               // exact: integer tokens × integer nano-dollar rates
            $usage->inputText * $rates->inputText
            + $usage->inputImage * $rates->inputImage
            + $usage->output * $rates->output
            + $usage->reasoning * $rates->reasoning,
        );

        $markup = $cost->percent(self::MARKUP_BASIS_POINTS);  // the only rounding: half up, to 1 nano-dollar

        return new Price($cost, $markup, $cost->add($markup));
    }
}
