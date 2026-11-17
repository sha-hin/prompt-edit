<?php

use App\Money\InvalidAmount;
use App\Money\Money;

mutates(Money::class);

it('parses decimal dollar strings exactly', function (string $dollars, int $nanos): void {
    expect(Money::fromDecimal($dollars)->nanos)->toBe($nanos);
})->with([
    'Pixelforge cost_usd' => ['0.011685000', 11_685_000],
    'rate per 1M tokens' => ['40.00', 40_000_000_000],
    'whole dollars' => ['1', 1_000_000_000],
    'one nano-dollar' => ['0.000000001', 1],
    'negative' => ['-0.5', -500_000_000],
    'largest allowed' => ['999999999.999999999', 999_999_999_999_999_999],
]);

it('rejects strings that are not exact dollar amounts', function (string $dollars): void {
    Money::fromDecimal($dollars);
})->throws(InvalidAmount::class)->with([
    'empty' => [''],
    'words' => ['abc'],
    'ten decimals' => ['1.0000000001'],
    'exponent' => ['1e3'],
    'two points' => ['0.1.2'],
    'padded' => [' 1'],
    'thousands separator' => ['1,000'],
    'no whole part' => ['.5'],
    'trailing point' => ['5.'],
    'too large for an int' => ['1234567890'],
]);

it('writes the exact amount with 9 decimals', function (int $nanos, string $decimal): void {
    expect(Money::nanos($nanos)->toDecimal())->toBe($decimal);
})->with([
    [14_606_250, '0.014606250'],
    [1_500_000_000, '1.500000000'],
    [0, '0.000000000'],
    [-1, '-0.000000001'],
]);

it('formats for people, rounded half up', function (int $nanos, int $decimals, string $formatted): void {
    expect(Money::nanos($nanos)->format($decimals))->toBe($formatted);
})->with([
    'a typical charge' => [14_606_250, 4, '$0.0146'],
    'half rounds up' => [50_000, 4, '$0.0001'],
    'below half rounds down' => [49_999, 4, '$0.0000'],
    'negative' => [-14_606_250, 4, '-$0.0146'],
    'cents' => [1_995_000_000, 2, '$2.00'],
    'whole dollars' => [1_500_000_000, 0, '$2'],
    'every digit' => [14_606_250, 9, '$0.014606250'],
]);

it('keeps decimals between 0 and 9 when formatting', function (): void {
    $money = Money::nanos(1_500_000_001);

    expect($money->format(-1))->toBe('$2')
        ->and($money->format(10))->toBe('$1.500000001');
});

it('formats with 4 decimals by default', function (): void {
    expect(Money::nanos(467_343_750)->format())->toBe('$0.4673');
});

it('adds and multiplies without changing the original', function (): void {
    $money = Money::nanos(10);

    expect($money->add(Money::nanos(5))->nanos)->toBe(15)
        ->and($money->times(3)->nanos)->toBe(30)
        ->and($money->nanos)->toBe(10)
        ->and(Money::zero()->nanos)->toBe(0);
});

it('takes a percentage in basis points, rounding half away from zero', function (int $nanos, int $expected): void {
    expect(Money::nanos($nanos)->percent(2_500)->nanos)->toBe($expected);
})->with([
    'exact' => [11_685_000, 2_921_250],
    'quarter rounds down' => [1, 0],
    'half rounds up' => [2, 1],
    'three quarters round up' => [3, 1],
    'negative half rounds away from zero' => [-2, -1],
    'negative quarter rounds to zero' => [-1, 0],
]);

it('rounds at exactly half a nano-dollar for any basis points', function (int $nanos, int $expected): void {
    expect(Money::nanos($nanos)->percent(1)->nanos)->toBe($expected);        // 1 bp = 1/10,000
})->with([
    'just below half' => [4_999, 0],
    'half' => [5_000, 1],
]);

it('compares amounts by value', function (): void {
    expect(Money::nanos(7)->equals(Money::nanos(7)))->toBeTrue()
        ->and(Money::nanos(7)->equals(Money::nanos(8)))->toBeFalse();
});
