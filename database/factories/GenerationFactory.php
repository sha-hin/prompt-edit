<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'prompt' => fake()->sentence(),
            'size' => ImageSize::Square,
            'quality' => ImageQuality::Medium,
            'reference_images' => [],
            'status' => GenerationStatus::Pending,
            'estimate_min_nanos' => 58_137_500,
            'estimate_max_nanos' => 86_275_000,
        ];
    }

    /**
     * Indicate that Pixelforge has accepted the generation.
     */
    public function queued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GenerationStatus::Queued,
            'pixelforge_id' => 'gen_' . Str::ulid(),
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
        ]);
    }
}
