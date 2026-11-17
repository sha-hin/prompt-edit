<?php

namespace App\Money;

/**
 * An exact amount of US dollars, held as a whole number of nano-dollars (1e-9 USD).
 *
 * Nano-dollars because Pixelforge reports costs with 9 decimals, so every provider
 * cost is an exact integer here. There is no currency field on purpose: the app
 * bills in USD only (Pixelforge charges USD and credits are US dollars).
 */
final readonly class Money
{
    public const int NANOS_PER_DOLLAR = 1_000_000_000;

    private function __construct(public int $nanos) {}

    public static function nanos(int $nanos): self
    {
        return new self($nanos);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse a decimal dollar string such as "0.011685000" without touching floats.
     */
    public static function fromDecimal(string $dollars): self
    {
        if (preg_match('/^(-?)(\d{1,9})(?:\.(\d{1,9}))?$/', $dollars, $parts) !== 1) {
            throw InvalidAmount::fromDecimal($dollars);   // also caps at $999,999,999 so nanos fit an int
        }

        $nanos = (int) $parts[2] * self::NANOS_PER_DOLLAR + (int) str_pad($parts[3] ?? '', 9, '0');

        return new self($parts[1] === '-' ? -$nanos : $nanos);
    }

    public function add(self $other): self
    {
        return new self($this->nanos + $other->nanos);
    }

    public function times(int $factor): self
    {
        return new self($this->nanos * $factor);
    }

    /**
     * A share of this amount in basis points (2_500 = 25%), rounded half up, away from zero.
     */
    public function percent(int $basisPoints): self
    {
        $product = $this->nanos * $basisPoints;
        $rounded = intdiv(abs($product) + 5_000, 10_000);

        return new self($product < 0 ? -$rounded : $rounded);
    }

    public function equals(self $other): bool
    {
        return $this->nanos === $other->nanos;
    }

    /**
     * The exact amount as a 9-decimal string, for JSON and comparing with Pixelforge's cost_usd.
     */
    public function toDecimal(): string
    {
        return $this->sign() . $this->digits(abs($this->nanos), 9);
    }

    /**
     * For people only, rounded half up to $decimals places. Never parse this back into money.
     */
    public function format(int $decimals = 4): string
    {
        $decimals = max(0, min(9, $decimals));
        $unit = 10 ** (9 - $decimals);
        $rounded = intdiv(abs($this->nanos) + intdiv($unit, 2), $unit) * $unit;

        return $this->sign() . '$' . rtrim($this->digits($rounded, $decimals), '.');
    }

    private function sign(): string
    {
        return $this->nanos < 0 ? '-' : '';
    }

    private function digits(int $nanos, int $decimals): string
    {
        $fraction = str_pad((string) ($nanos % self::NANOS_PER_DOLLAR), 9, '0', STR_PAD_LEFT);

        return intdiv($nanos, self::NANOS_PER_DOLLAR) . '.' . substr($fraction, 0, $decimals);
    }
}
