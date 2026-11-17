<?php

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\GenerationSpec;
use App\Pricing\CostEstimate;
use App\Pricing\GenerationEstimator;
use App\Pricing\PriceCalculator;
use App\Pricing\Rates;
use App\Pricing\TokenUsage;

mutates(GenerationEstimator::class, ImageQuality::class);

function publishedRates(): Rates
{
    return new Rates(inputText: 5_000, inputImage: 10_000, output: 40_000, reasoning: 12_500);
}

function estimate(GenerationSpec $spec): CostEstimate
{
    return new GenerationEstimator(new PriceCalculator)->estimate($spec, publishedRates());
}

it('matches the worked maximum from the Pixelforge docs', function (): void {
    // high, 1536x1024, 400-char prompt, 2 reference images: "costs at most $0.373875"
    $estimate = estimate(new GenerationSpec(
        prompt: str_repeat('a', 400),
        size: ImageSize::Landscape,
        quality: ImageQuality::High,
        referenceImages: ['https://example.com/a.png', 'https://example.com/b.png'],
    ));

    expect($estimate->maxUsage)->toEqual(new TokenUsage(inputText: 115, inputImage: 1_530, output: 6_450, reasoning: 8_000))
        ->and($estimate->max->cost->toDecimal())->toBe('0.373875000')
        ->and($estimate->max->charge->toDecimal())->toBe('0.467343750')
        ->and($estimate->minUsage)->toEqual(new TokenUsage(inputText: 85, inputImage: 1_530, output: 6_450, reasoning: 1_000))
        ->and($estimate->min->cost->toDecimal())->toBe('0.286225000')
        ->and($estimate->min->charge->toDecimal())->toBe('0.357781250');
});

it('uses the exact output tokens and the reasoning range for every size and quality', function (
    ImageSize $size,
    ImageQuality $quality,
    int $output,
    int $minReasoning,
    int $maxReasoning,
): void {
    $estimate = estimate(new GenerationSpec(prompt: '', size: $size, quality: $quality));

    expect($estimate->minUsage->output)->toBe($output)
        ->and($estimate->maxUsage->output)->toBe($output)
        ->and($estimate->minUsage->reasoning)->toBe($minReasoning)
        ->and($estimate->maxUsage->reasoning)->toBe($maxReasoning);
})->with([
    [ImageSize::Square, ImageQuality::Low, 280, 0, 500],
    [ImageSize::Landscape, ImageQuality::Low, 420, 0, 500],
    [ImageSize::Portrait, ImageQuality::Low, 420, 0, 500],
    [ImageSize::Square, ImageQuality::Medium, 1_100, 200, 2_000],
    [ImageSize::Landscape, ImageQuality::Medium, 1_650, 200, 2_000],
    [ImageSize::Portrait, ImageQuality::Medium, 1_650, 200, 2_000],
    [ImageSize::Square, ImageQuality::High, 4_300, 1_000, 8_000],
    [ImageSize::Landscape, ImageQuality::High, 6_450, 1_000, 8_000],
    [ImageSize::Portrait, ImageQuality::High, 6_450, 1_000, 8_000],
]);

it('estimates text tokens as ceil(chars / 4) within ±15%', function (string $prompt, int $min, int $max): void {
    $estimate = estimate(new GenerationSpec(prompt: $prompt));

    expect($estimate->minUsage->inputText)->toBe($min)
        ->and($estimate->maxUsage->inputText)->toBe($max);
})->with([
    'empty' => ['', 0, 0],
    'one char is one token' => ['a', 0, 2],
    'rounds chars up' => ['a red cube', 2, 4],          // 10 chars -> 3 tokens
    'exact quarter' => [str_repeat('a', 400), 85, 115],
    'longest prompt' => [str_repeat('a', 4_000), 850, 1_150],
    'counts characters, not bytes' => [str_repeat('é', 8), 1, 3],   // 8 chars (16 bytes) -> 2 tokens
]);

it('bills 765 tokens per reference image', function (int $images, int $tokens): void {
    $estimate = estimate(new GenerationSpec(
        prompt: '',
        referenceImages: array_fill(0, $images, 'https://example.com/a.png'),
    ));

    expect($estimate->minUsage->inputImage)->toBe($tokens)
        ->and($estimate->maxUsage->inputImage)->toBe($tokens);
})->with([[0, 0], [1, 765], [4, 3_060]]);
