<?php

namespace App\Pricing;

use App\Money\Money;

/**
 * Pixelforge's rates, in nano-dollars per single token.
 */
final readonly class Rates
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    /**
     * Build from Pixelforge's "USD per 1M tokens" strings, the shape of `rates` in the API.
     *
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $perMillion
     */
    public static function fromPerMillion(array $perMillion): self
    {
        return new self(
            self::perToken($perMillion['input_text_tokens']),
            self::perToken($perMillion['input_image_tokens']),
            self::perToken($perMillion['output_tokens']),
            self::perToken($perMillion['reasoning_tokens']),
        );
    }

    private static function perToken(string $usdPerMillion): int
    {
        $nanos = Money::fromDecimal($usdPerMillion)->nanos;

        if ($nanos % 1_000_000 !== 0) {
            throw InvalidRate::notExact($usdPerMillion);   // more than 3 decimals: not a whole nano-dollar per token
        }

        return intdiv($nanos, 1_000_000);
    }
}
