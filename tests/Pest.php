<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
| Pest-style tests in Feature and Unit run on a fresh database, with the clock frozen and
| outgoing HTTP blocked, so no test can reach the real Pixelforge API by accident.
| Use Http::fake() or a fake client bound in the container inside the test.
|
| The class-based tests that came with the starter kit (auth, settings) keep their own setup.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 10:00:00', 'UTC'));
        Http::preventStrayRequests();
    })
    ->in('Feature', 'Unit');

/**
 * A real Pixelforge response body from tests/Fixtures/pixelforge, merged with $overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgeJson(string $name, array $overrides = []): array
{
    /** @var array<string, mixed> $json */
    $json = json_decode((string) file_get_contents(__DIR__ . "/Fixtures/pixelforge/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);

    return array_replace($json, $overrides);
}

/**
 * Answer GET /v1/pricing with the published rates, or with $rates instead.
 *
 * @param  array<string, string>  $rates
 */
function fakePricing(array $rates = []): void
{
    Http::fake(['*/pricing' => Http::response(pixelforgeJson('pricing', $rates === [] ? [] : ['rates' => $rates]))]);
}

/**
 * Pixelforge's error body, as documented: { "error": { "type", "code", "message" } }.
 *
 * @return array{error: array{type: string, code: string, message: string}}
 */
function pixelforgeError(string $code, string $message = 'Something went wrong.'): array
{
    return ['error' => ['type' => 'test_error', 'code' => $code, 'message' => $message]];
}
