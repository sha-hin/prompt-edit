<?php

namespace App\Pricing;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    public function total(): int
    {
        return $this->inputText + $this->inputImage + $this->output + $this->reasoning;
    }
}
