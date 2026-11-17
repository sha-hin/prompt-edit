<?php

namespace App\Generations;

use App\Credits\CreditLedger;
use App\Credits\InsufficientCredits;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pricing\GenerationEstimator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Save what the user asked for, with its estimate, and queue it for Pixelforge, but only if the
 * user can afford the estimate's maximum. Saving the row is what reserves that amount.
 */
final readonly class CreateGeneration
{
    public function __construct(
        private GenerationEstimator $estimator,
        private PixelforgeClient $pixelforge,
        private CreditLedger $ledger,
    ) {}

    /**
     * @throws InsufficientCredits
     */
    public function handle(User $user, GenerationSpec $spec): Generation
    {
        $estimate = $this->estimator->estimate($spec, $this->pixelforge->pricing());     // may call Pixelforge: kept outside the lock

        return DB::transaction(function () use ($user, $spec, $estimate): Generation {
            $user = $this->ledger->lockAndEnsureCanAfford($user, $estimate->max->charge);

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

            SubmitGeneration::dispatch($generation);        // after commit, so the job never sees a rolled-back row

            return $generation;
        });
    }
}
