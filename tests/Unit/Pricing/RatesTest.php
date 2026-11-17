<?php

use App\Pricing\InvalidRate;
use App\Pricing\Rates;

mutates(Rates::class);

it('converts the published per-1M rates to nano-dollars per token', function (): void {
    $rates = Rates::fromPerMillion([
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ]);

    expect($rates)->toEqual(new Rates(inputText: 5_000, inputImage: 10_000, output: 40_000, reasoning: 12_500));
});

it('converts the smallest and a large rate exactly', function (string $usdPerMillion, int $nanosPerToken): void {
    $rates = Rates::fromPerMillion([
        'input_text_tokens' => $usdPerMillion,
        'input_image_tokens' => '0',
        'output_tokens' => '0',
        'reasoning_tokens' => '0',
    ]);

    expect($rates->inputText)->toBe($nanosPerToken);
})->with([
    'one nano-dollar per token' => ['0.001', 1],
    'a thousand dollars per 1M' => ['1000.00', 1_000_000],
]);

it('rejects a rate that is not a whole nano-dollar per token', function (string $field): void {
    $perMillion = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];
    $perMillion[$field] = '0.0005';

    Rates::fromPerMillion($perMillion);
})->throws(InvalidRate::class)->with(['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens']);
