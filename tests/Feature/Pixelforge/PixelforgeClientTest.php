<?php

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\GenerationSpec;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use App\Pricing\Rates;
use App\Pricing\TokenUsage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

function pixelforge(): PixelforgeClient
{
    return app(PixelforgeClient::class);
}

function createCube(): void
{
    pixelforge()->createGeneration(new GenerationSpec('a red cube'), 'key-123', []);
}

it('creates a generation with the idempotency key and metadata', function (): void {
    Http::fake(['*/generations' => Http::response(pixelforgeJson('generation-queued'), 202)]);

    $generation = pixelforge()->createGeneration(
        new GenerationSpec('a red cube', ImageSize::Landscape, ImageQuality::High, ['https://example.com/a.png']),
        'key-123',
        ['generation_id' => '01local'],
    );

    expect($generation->id)->toBe('gen_01M7N6J8PJWNEKE6XW84S74ZV9')
        ->and($generation->status)->toBe('queued')
        ->and($generation->usage)->toBeNull()
        ->and($generation->imageUrl)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://pixelforge.test/v1/generations'
        && $request->hasHeader('Authorization', 'Bearer pf_test_not_a_real_key')
        && $request->hasHeader('Idempotency-Key', 'key-123')
        && $request->data() === [
            'prompt' => 'a red cube',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png'],
            'metadata' => ['generation_id' => '01local'],
        ]);
});

it('leaves out empty reference images and metadata', function (): void {
    Http::fake(['*/generations' => Http::response(pixelforgeJson('generation-queued'), 202)]);

    createCube();

    Http::assertSent(fn (Request $request): bool => $request->data() === [
        'prompt' => 'a red cube',
        'size' => '1024x1024',
        'quality' => 'medium',
    ]);
});

it('reads a succeeded generation with its usage, locked rates and image', function (): void {
    Http::fake(['*/generations/gen_01M7N6J8PJWNEKE6XW84S74ZV9' => Http::response(pixelforgeJson('generation-succeeded'))]);

    $generation = pixelforge()->getGeneration('gen_01M7N6J8PJWNEKE6XW84S74ZV9');

    expect($generation->status)->toBe('succeeded')
        ->and($generation->usage)->toEqual(new TokenUsage(inputText: 12, inputImage: 0, output: 280, reasoning: 34))
        ->and($generation->rates)->toBe([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ])
        ->and($generation->imageUrl)->toStartWith('https://pixelforge.promptedit.dev/images/gen_01M7N6J8PJWNEKE6XW84S74ZV9.png')
        ->and($generation->imageUrlExpiresAt?->equalTo(CarbonImmutable::parse('2026-11-18T16:49:08Z')))->toBeTrue()
        ->and($generation->errorCode)->toBeNull();
});

it('reads why a generation failed', function (): void {
    Http::fake(['*/generations/*' => Http::response(pixelforgeJson('generation-queued', [
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'No image could be made for this prompt.'],
    ]))]);

    $generation = pixelforge()->getGeneration('gen_01M7N6J8PJWNEKE6XW84S74ZV9');

    expect($generation->status)->toBe('failed')
        ->and($generation->errorCode)->toBe('generation_failed')
        ->and($generation->errorMessage)->toBe('No image could be made for this prompt.');
});

it('turns Pixelforge errors into an exception that says whether to retry', function (
    int $status,
    mixed $body,
    array $headers,
    string $code,
    bool $retryable,
    int $retryAfter,
): void {
    Http::fake(['*/generations' => Http::response($body, $status, $headers)]);

    try {
        createCube();
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $e) {
        expect($e->status)->toBe($status)
            ->and($e->errorCode)->toBe($code)
            ->and($e->isRetryable())->toBe($retryable)
            ->and($e->retryAfter)->toBe($retryAfter);
    }
})->with([
    'per-minute limit' => [429, pixelforgeError('rate_limit_exceeded'), ['Retry-After' => '12'], 'rate_limit_exceeded', true, 12],
    'concurrency limit' => [429, pixelforgeError('concurrency_limit_exceeded'), ['Retry-After' => '5'], 'concurrency_limit_exceeded', true, 5],
    'overloaded' => [503, pixelforgeError('overloaded'), ['Retry-After' => '2'], 'overloaded', true, 2],
    'server error without a JSON body' => [500, 'oops', [], 'http_500', true, 5],
    'daily limit' => [429, pixelforgeError('daily_limit_exceeded'), [], 'daily_limit_exceeded', false, 5],
    'blocked prompt' => [400, pixelforgeError('content_policy_violation'), [], 'content_policy_violation', false, 5],
    'invalid key' => [401, pixelforgeError('invalid_api_key'), [], 'invalid_api_key', false, 5],
    'idempotency key reused' => [409, pixelforgeError('idempotency_key_reused'), [], 'idempotency_key_reused', false, 5],
    'validation failed' => [422, pixelforgeError('validation_failed'), [], 'validation_failed', false, 5],
    'Retry-After as a date' => [503, pixelforgeError('overloaded'), ['Retry-After' => 'Wed, 18 Nov 2026 10:00:00 GMT'], 'overloaded', true, 5],
    'Retry-After of zero' => [429, pixelforgeError('rate_limit_exceeded'), ['Retry-After' => '0'], 'rate_limit_exceeded', true, 1],
]);

it('keeps Pixelforge\'s error message', function (): void {
    Http::fake(['*/generations' => Http::response(pixelforgeError('content_policy_violation', 'Prompt rejected.'), 400)]);

    expect(fn () => createCube())->toThrow(PixelforgeException::class, 'Prompt rejected.');
});

it('treats an unexpected success body as a retryable error', function (mixed $body): void {
    Http::fake(['*/generations' => Http::response($body, 202)]);

    try {
        createCube();
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $e) {
        expect($e->errorCode)->toBe('malformed_response')
            ->and($e->isRetryable())->toBeTrue();
    }
})->with([
    'not JSON' => ['<html>Bad gateway</html>'],
    'no id' => [['status' => 'queued']],
    'no rates' => [['id' => 'gen_1', 'status' => 'queued']],
    'usage with a missing count' => [pixelforgeJson('generation-succeeded', ['usage' => ['input_text_tokens' => 12]])],
]);

it('lets connection errors through for the caller to retry', function (): void {
    Http::fake(['*/generations' => Http::failedConnection()]);

    expect(fn () => createCube())->toThrow(ConnectionException::class);
});

it('caches the published rates for an hour', function (): void {
    fakePricing();

    expect(pixelforge()->pricing())->toEqual(new Rates(5_000, 10_000, 40_000, 12_500))
        ->and(pixelforge()->pricing())->toEqual(new Rates(5_000, 10_000, 40_000, 12_500));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pixelforge.test/v1/pricing');

    $this->travel(PixelforgeClient::PRICING_CACHE_SECONDS + 1)->seconds();
    pixelforge()->pricing();

    Http::assertSentCount(2);
});

it('falls back to the configured rates when pricing is down, without caching them', function (mixed $response): void {
    Exceptions::fake();
    Http::fake(['*/pricing' => $response]);

    expect(pixelforge()->pricing())->toEqual(new Rates(5_000, 10_000, 40_000, 12_500))
        ->and(Cache::has(PixelforgeClient::PRICING_CACHE_KEY))->toBeFalse();

    Exceptions::assertReportedCount(1);
})->with([
    'overloaded' => fn () => Http::response(pixelforgeError('overloaded'), 503),
    'unreachable' => fn () => Http::failedConnection(),
    'no rates in the body' => fn () => Http::response(['object' => 'pricing']),
]);
