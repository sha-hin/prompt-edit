<?php

namespace App\Enums;

/**
 * Token counts per quality, from the Pixelforge pricing tables.
 */
enum ImageQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Output tokens are exact for a quality and size.
     */
    public function outputTokens(ImageSize $size): int
    {
        $square = $size === ImageSize::Square;      // both non-square sizes cost the same

        return match ($this) {
            self::Low => $square ? 280 : 420,
            self::Medium => $square ? 1_100 : 1_650,
            self::High => $square ? 4_300 : 6_450,
        };
    }

    public function minReasoningTokens(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 200,
            self::High => 1_000,
        };
    }

    public function maxReasoningTokens(): int
    {
        return match ($this) {
            self::Low => 500,
            self::Medium => 2_000,
            self::High => 8_000,
        };
    }
}
