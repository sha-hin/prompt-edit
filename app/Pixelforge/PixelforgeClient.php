<?php

namespace App\Pixelforge;

use App\Generations\GenerationSpec;
use App\Pricing\Rates;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Pixelforge API. One attempt per call: retrying is the queued job's decision.
 */
final readonly class PixelforgeClient
{
    public const string PRICING_CACHE_KEY = 'pixelforge:pricing';

    public const int PRICING_CACHE_SECONDS = 3_600;

    /**
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $fallbackRates
     */
    public function __construct(
        private string $baseUrl,
        private string $key,
        private int $timeout,
        private int $connectTimeout,
        private array $fallbackRates,
    ) {}

    /**
     * @param  array<string, string>  $metadata
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function createGeneration(GenerationSpec $spec, string $idempotencyKey, array $metadata): PixelforgeGeneration
    {
        $response = $this->request()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('generations', array_filter([
                'prompt' => $spec->prompt,
                'size' => $spec->size->value,
                'quality' => $spec->quality->value,
                'reference_images' => $spec->referenceImages,
                'metadata' => $metadata,
            ], fn (mixed $value): bool => $value !== []));             // an empty [] would be sent as a JSON list, not an object

        return PixelforgeGeneration::fromResponse($this->json($response));
    }

    /**
     * @throws PixelforgeException|ConnectionException
     */
    public function getGeneration(string $id): PixelforgeGeneration
    {
        return PixelforgeGeneration::fromResponse($this->json($this->request()->get('generations/' . rawurlencode($id))));
    }

    /**
     * Current rates for estimates, cached for an hour. The charge uses the rates locked on each generation instead.
     */
    public function pricing(): Rates
    {
        try {
            $rates = Cache::remember(self::PRICING_CACHE_KEY, self::PRICING_CACHE_SECONDS, fn (): array => PixelforgeGeneration::rates(
                $this->json($this->request()->get('pricing'))['rates'] ?? null,
            ));
        } catch (PixelforgeException|ConnectionException $e) {
            report($e);
            $rates = $this->fallbackRates;                          // not cached, so the next estimate tries again
        }

        return Rates::fromPerMillion($rates);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->key)
            ->acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * @return array<mixed>
     *
     * @throws PixelforgeException
     */
    private function json(Response $response): array
    {
        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw PixelforgeException::malformedResponse('the body is not JSON');
        }

        return $json;
    }
}
