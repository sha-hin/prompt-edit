<?php

namespace App\Pixelforge;

use App\Generations\ApplyGenerationUpdate;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;

/**
 * Store a verified event once and apply it, all or nothing: if applying fails, the
 * event isn't stored either, we answer 5xx, and Pixelforge delivers it again.
 */
final readonly class ProcessPixelforgeEvent
{
    public function __construct(private ApplyGenerationUpdate $applyGenerationUpdate) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(string $eventId, string $type, array $payload): void
    {
        DB::transaction(function () use ($eventId, $type, $payload): void {
            $event = WebhookEvent::createOrFirst(['event_id' => $eventId], ['type' => $type, 'payload' => $payload]);

            if (! $event->wasRecentlyCreated) {
                return;                                     // a repeated delivery: already applied
            }

            if (str_starts_with($type, 'generation.')) {    // generation.processing, .succeeded, .failed; ping needs nothing
                $object = $payload['data']['object'] ?? null;

                $this->applyGenerationUpdate->handle(PixelforgeGeneration::fromResponse(is_array($object) ? $object : []));
            }
        });
    }
}
