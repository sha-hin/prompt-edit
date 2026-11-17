<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';           // saved, not yet accepted by Pixelforge
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * Statuses only move forward, and never out of a final one, so late or repeated events can't undo a result.
     */
    public function canMoveTo(self $next): bool
    {
        return ! $this->isFinal() && $next->step() > $this->step();
    }

    private function step(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
