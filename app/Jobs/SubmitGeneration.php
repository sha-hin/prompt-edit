<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Send a pending generation to Pixelforge. Safe to run many times: every attempt
 * sends the same Idempotency-Key, so Pixelforge creates it at most once.
 */
#[MaxExceptions(3)] // network errors and timeouts; releases don't count
#[Timeout(30)]
#[UniqueFor(660)] // retryUntil plus a minute; a lost job unblocks after this
final class SubmitGeneration implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Generation $generation)
    {
        $this->onQueue('pixelforge');
    }

    /**
     * One job per generation: the sweep re-dispatching a pending one is a no-op while this one waits out a Retry-After.
     */
    public function uniqueId(): string
    {
        return $this->generation->id;
    }

    public function handle(PixelforgeClient $pixelforge): void
    {
        if ($this->generation->status !== GenerationStatus::Pending) {
            return;                                         // already accepted, or moved on by a webhook
        }

        try {
            $accepted = $pixelforge->createGeneration(
                $this->generation->spec(),
                $this->generation->idempotency_key,
                ['generation_id' => $this->generation->id],      // lets a webhook find us before this call returns
            );
        } catch (PixelforgeException $e) {
            if ($e->isRetryable()) {
                $this->release($e->retryAfter);             // 429 or 503: wait as long as Pixelforge asks

                return;
            }

            $this->markFailed($e->errorCode, $e->getMessage());

            return;
        }

        $this->pending()->update([
            'pixelforge_id' => $accepted->id,
            'rates' => json_encode($accepted->rates, JSON_THROW_ON_ERROR),
            'status' => GenerationStatus::Queued,
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);                       // the concurrency limit can hold a job back for minutes
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('submission_failed', 'Pixelforge is busy right now. Please try again.');
    }

    private function markFailed(string $code, string $message): void
    {
        $this->pending()->update([                          // failed before Pixelforge accepted it: nothing to bill
            'status' => GenerationStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'updated_at' => now(),
        ]);
    }

    /**
     * Only a still-pending row may change here, so a webhook that got in first is never overwritten.
     *
     * @return Builder<Generation>
     */
    private function pending(): Builder
    {
        return Generation::query()
            ->whereKey($this->generation->id)
            ->where('status', GenerationStatus::Pending);
    }
}
