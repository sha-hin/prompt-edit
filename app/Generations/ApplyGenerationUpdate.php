<?php

namespace App\Generations;

use App\Credits\CreditLedger;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PixelforgeGeneration;
use App\Pricing\PriceCalculator;
use App\Pricing\Rates;
use Illuminate\Support\Facades\DB;

/**
 * Bring our row up to date with what Pixelforge reports, from a webhook or a poll.
 *
 * The row is locked and final statuses never change, so the charge is written once:
 * in the same transaction that makes the generation succeeded, along with the ledger entry.
 */
final readonly class ApplyGenerationUpdate
{
    public function __construct(
        private PriceCalculator $calculator,
        private CreditLedger $ledger,
    ) {}

    /**
     * @return Generation|null null when the generation isn't ours
     */
    public function handle(PixelforgeGeneration $snapshot): ?Generation
    {
        return DB::transaction(function () use ($snapshot): ?Generation {
            $generation = $this->findLocked($snapshot);

            if (! $generation instanceof Generation) {
                return null;
            }

            $generation->forceFill([                        // a webhook can beat SubmitGeneration to these
                'pixelforge_id' => $generation->pixelforge_id ?? $snapshot->id,
                'rates' => $generation->rates ?? $snapshot->rates,
            ]);

            $next = GenerationStatus::tryFrom($snapshot->status);

            if ($next !== null && $generation->status->canMoveTo($next)) {
                $generation->forceFill(['status' => $next]);

                match ($next) {
                    GenerationStatus::Succeeded => $this->succeed($generation, $snapshot),
                    GenerationStatus::Failed => $this->fail($generation, $snapshot),
                    default => null,
                };
            }

            $generation->save();

            return $generation;
        });
    }

    private function findLocked(PixelforgeGeneration $snapshot): ?Generation
    {
        $byPixelforgeId = Generation::query()->where('pixelforge_id', $snapshot->id)->lockForUpdate()->first();

        if ($byPixelforgeId instanceof Generation || ! isset($snapshot->metadata['generation_id'])) {
            return $byPixelforgeId;
        }

        return Generation::query()                          // the create call hasn't returned yet: find it by our own id
            ->whereKey($snapshot->metadata['generation_id'])
            ->whereNull('pixelforge_id')
            ->lockForUpdate()
            ->first();
    }

    private function succeed(Generation $generation, PixelforgeGeneration $snapshot): void
    {
        if ($snapshot->usage === null || $generation->rates === null) {
            throw PixelforgeException::malformedResponse('a succeeded generation without usage');
        }

        $price = $this->calculator->price($snapshot->usage, Rates::fromPerMillion($generation->rates));  // the rates locked at create

        $generation->forceFill([
            'usage' => [
                'input_text_tokens' => $snapshot->usage->inputText,
                'input_image_tokens' => $snapshot->usage->inputImage,
                'output_tokens' => $snapshot->usage->output,
                'reasoning_tokens' => $snapshot->usage->reasoning,
            ],
            'cost_nanos' => $price->cost->nanos,
            'charge_nanos' => $price->charge->nanos,
            'image_url' => $snapshot->imageUrl,
            'image_url_expires_at' => $snapshot->imageUrlExpiresAt,
            'completed_at' => $snapshot->completedAt ?? now(),
        ]);

        $this->ledger->charge($generation);
    }

    private function fail(Generation $generation, PixelforgeGeneration $snapshot): void
    {
        $generation->forceFill([                            // failed generations are free
            'cost_nanos' => 0,
            'charge_nanos' => 0,
            'error_code' => $snapshot->errorCode ?? 'generation_failed',
            'error_message' => $snapshot->errorMessage ?? 'Pixelforge could not make this image.',
            'completed_at' => $snapshot->completedAt ?? now(),
        ]);
    }
}
