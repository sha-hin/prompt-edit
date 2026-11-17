<?php

namespace App\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * What a user asks Pixelforge to generate.
 */
final readonly class GenerationSpec
{
    /**
     * @param  list<string>  $referenceImages
     */
    public function __construct(
        public string $prompt,
        public ImageSize $size = ImageSize::Square,           // Pixelforge's defaults
        public ImageQuality $quality = ImageQuality::Medium,
        public array $referenceImages = [],
    ) {}
}
