<?php

use App\Pricing\PriceCalculator;
use App\Pricing\Rates;
use App\Pricing\TokenUsage;

mutates(PriceCalculator::class);

it('prices a real Pixelforge generation exactly', function (): void {
    // gen_01M7N6J8PJWNEKE6XW84S74ZV9: low 1024x1024, 12 + 0 + 280 + 34 tokens, cost_usd 0.011685
    $price = (new PriceCalculator)->price(
        new TokenUsage(inputText: 12, inputImage: 0, output: 280, reasoning: 34),
        new Rates(inputText: 5_000, inputImage: 10_000, output: 40_000, reasoning: 12_500),
    );

    expect($price->cost->toDecimal())->toBe('0.011685000')
        ->and($price->markup->toDecimal())->toBe('0.002921250')
        ->and($price->charge->toDecimal())->toBe('0.014606250');
});

it('charges each token type at its own rate', function (TokenUsage $usage, int $cost): void {
    $price = (new PriceCalculator)->price($usage, new Rates(inputText: 1, inputImage: 10, output: 100, reasoning: 1_000));

    expect($price->cost->nanos)->toBe($cost);
})->with([
    'input text' => [new TokenUsage(7, 0, 0, 0), 7],
    'input image' => [new TokenUsage(0, 7, 0, 0), 70],
    'output' => [new TokenUsage(0, 0, 7, 0), 700],
    'reasoning' => [new TokenUsage(0, 0, 0, 7), 7_000],
    'all together' => [new TokenUsage(1, 1, 1, 1), 1_111],
]);

it('rounds the 25% markup once, half up, to a nano-dollar', function (int $cost, int $markup): void {
    $price = (new PriceCalculator)->price(new TokenUsage($cost, 0, 0, 0), new Rates(1, 0, 0, 0));

    expect($price->markup->nanos)->toBe($markup)
        ->and($price->charge->nanos)->toBe($cost + $markup);
})->with([
    'quarter rounds down' => [1, 0],
    'half rounds up' => [2, 1],
    'three quarters round up' => [3, 1],
    'exact' => [4, 1],
]);

it('charges nothing for no usage', function (): void {
    $price = (new PriceCalculator)->price(new TokenUsage(0, 0, 0, 0), new Rates(5_000, 10_000, 40_000, 12_500));

    expect($price->charge->nanos)->toBe(0);
});
