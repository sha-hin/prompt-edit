<?php

namespace App\Pixelforge;

use App\Pricing\TokenUsage;
use Carbon\CarbonImmutable;

/**
 * A generation as Pixelforge reports it, checked and typed at the edge.
 */
final readonly class PixelforgeGeneration
{
    private const array TOKEN_KEYS = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /**
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     */
    public function __construct(
        public string $id,
        public string $status,
        public array $rates,
        public ?TokenUsage $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?string $errorCode,
        public ?string $errorMessage,
    ) {}

    /**
     * @param  array<mixed>  $json
     */
    public static function fromResponse(array $json): self
    {
        if (! is_string($json['id'] ?? null) || ! is_string($json['status'] ?? null)) {
            throw PixelforgeException::malformedResponse('a generation without id or status');
        }

        return new self(
            id: $json['id'],
            status: $json['status'],
            rates: self::rates($json['rates'] ?? null),
            usage: self::usage($json['usage'] ?? null),
            imageUrl: self::stringOrNull($json['image_url'] ?? null),
            imageUrlExpiresAt: is_string($json['image_url_expires_at'] ?? null) ? CarbonImmutable::parse($json['image_url_expires_at']) : null,
            errorCode: self::stringOrNull($json['error']['code'] ?? null),
            errorMessage: self::stringOrNull($json['error']['message'] ?? null),
        );
    }

    /**
     * @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}
     */
    public static function rates(mixed $rates): array
    {
        foreach (self::TOKEN_KEYS as $key) {
            if (! is_array($rates) || ! is_string($rates[$key] ?? null)) {
                throw PixelforgeException::malformedResponse("rates without {$key}");
            }
        }

        /** @var array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string} $rates */
        return array_intersect_key($rates, array_flip(self::TOKEN_KEYS));
    }

    private static function usage(mixed $usage): ?TokenUsage
    {
        if ($usage === null) {
            return null;                                            // null until the generation succeeds
        }

        foreach (self::TOKEN_KEYS as $key) {
            if (! is_array($usage) || ! is_int($usage[$key] ?? null)) {
                throw PixelforgeException::malformedResponse("usage without {$key}");
            }
        }

        /** @var array<string, int> $usage */
        return new TokenUsage($usage['input_text_tokens'], $usage['input_image_tokens'], $usage['output_tokens'], $usage['reasoning_tokens']);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
