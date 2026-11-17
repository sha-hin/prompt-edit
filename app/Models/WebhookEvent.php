<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Pixelforge event we have received and applied, kept once per event id.
 *
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property array<mixed> $payload
 * @property Carbon|null $created_at
 */
#[Fillable(['event_id', 'type', 'payload'])]
final class WebhookEvent extends Model
{
    public const null UPDATED_AT = null;                    // events never change once stored

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
