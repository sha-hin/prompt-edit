<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Generations\ApplyGenerationUpdate;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/**
 * The safety net, every minute: resubmit generations whose job was lost, and poll
 * those whose webhook never came (Pixelforge: "delivery isn't guaranteed").
 */
#[Description('Resubmit stuck pending generations and poll Pixelforge for ones with no webhook')]
#[Signature('generations:sweep')]
final class SweepGenerations extends Command
{
    public const int STALE_MINUTES = 2;                     // longer than a normal queue wait plus processing

    public const int POLL_LIMIT = 20;                       // Pixelforge allows 60 requests a minute, shared with submissions

    public function handle(PixelforgeClient $pixelforge, ApplyGenerationUpdate $applyGenerationUpdate): int
    {
        $stale = now()->subMinutes(self::STALE_MINUTES);

        Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->where('updated_at', '<', $stale)
            ->each(fn (Generation $generation) => SubmitGeneration::dispatch($generation));   // unique per generation, and idempotent

        Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', $stale)
            ->oldest('updated_at')
            ->limit(self::POLL_LIMIT)
            ->get()
            ->each(fn (Generation $generation) => $this->poll($generation, $pixelforge, $applyGenerationUpdate));

        return self::SUCCESS;
    }

    private function poll(Generation $generation, PixelforgeClient $pixelforge, ApplyGenerationUpdate $applyGenerationUpdate): void
    {
        try {
            $updated = $applyGenerationUpdate->handle($pixelforge->getGeneration((string) $generation->pixelforge_id));
        } catch (PixelforgeException $e) {
            if ($e->status === 404) {
                $this->markLost($generation);
            }

            return;                                         // busy or down: the next run tries again
        } catch (ConnectionException) {
            return;
        }

        if ($updated instanceof Generation && ! $updated->status->isFinal()) {
            $updated->touch();                              // still running: check again in STALE_MINUTES, oldest first
        }
    }

    private function markLost(Generation $generation): void
    {
        Generation::query()                                 // Pixelforge has no such generation: nothing to bill
            ->whereKey($generation->id)
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->update([
                'status' => GenerationStatus::Failed,
                'cost_nanos' => 0,
                'charge_nanos' => 0,
                'error_code' => 'not_found',
                'error_message' => 'Pixelforge lost this generation. Please try again.',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
