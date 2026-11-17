<?php

namespace App\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pricing\GenerationEstimator;
use Illuminate\Support\Str;

/**
 * Save what the user asked for, with its estimate, and queue it for Pixelforge.
 */
final readonly class CreateGeneration
{
    public function __construct(
        private GenerationEstimator $estimator,
        private PixelforgeClient $pixelforge,
    ) {}

    public function handle(User $user, GenerationSpec $spec): Generation
    {
        $estimate = $this->estimator->estimate($spec, $this->pixelforge->pricing());

        $generation = $user->generations()->make([
            'prompt' => $spec->prompt,
            'size' => $spec->size,
            'quality' => $spec->quality,
            'reference_images' => $spec->referenceImages,
        ])->forceFill([
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Pending,
            'estimate_min_nanos' => $estimate->min->charge->nanos,
            'estimate_max_nanos' => $estimate->max->charge->nanos,
        ]);

        $generation->save();

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
