<?php

namespace App\Pixelforge;

/**
 * GET /v1/usage: what Pixelforge billed this API key, per UTC day of completion.
 */
final readonly class PixelforgeUsage
{
    /**
     * @param  array<string, PixelforgeUsageTotals>  $days  keyed by Y-m-d
     */
    public function __construct(
        public array $days,
        public PixelforgeUsageTotals $total,
    ) {}

    /**
     * @param  array<mixed>  $json
     */
    public static function fromResponse(array $json): self
    {
        if (! is_array($json['data'] ?? null) || ! is_array($json['total'] ?? null)) {
            throw PixelforgeException::malformedResponse('usage without data or total');
        }

        $days = [];

        foreach ($json['data'] as $day) {
            if (! is_array($day) || ! is_string($day['date'] ?? null)) {
                throw PixelforgeException::malformedResponse('a usage entry without a date');
            }

            $days[$day['date']] = PixelforgeUsageTotals::fromResponse($day);
        }

        return new self($days, PixelforgeUsageTotals::fromResponse($json['total']));
    }
}
