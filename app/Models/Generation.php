<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\GenerationSpec;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id null once the user deleted their account; the record is kept for the books
 * @property string $idempotency_key
 * @property string|null $pixelforge_id
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property GenerationStatus $status
 * @property int $estimate_min_nanos
 * @property int $estimate_max_nanos
 * @property array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}|null $rates
 * @property array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}|null $usage
 * @property int|null $cost_nanos
 * @property int|null $charge_nanos
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['prompt', 'size', 'quality', 'reference_images'])] // status, costs and Pixelforge fields are system-written
final class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spec(): GenerationSpec
    {
        return new GenerationSpec($this->prompt, $this->size, $this->quality, $this->reference_images);
    }

    /**
     * Pixelforge keeps the image for 24 hours after completion, and we don't store a copy.
     */
    public function imageAvailable(): bool
    {
        return $this->status === GenerationStatus::Succeeded
            && $this->image_url !== null
            && ($this->image_url_expires_at === null || $this->image_url_expires_at->isFuture());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'status' => GenerationStatus::class,
            'estimate_min_nanos' => 'integer',
            'estimate_max_nanos' => 'integer',
            'rates' => 'array',
            'usage' => 'array',
            'cost_nanos' => 'integer',
            'charge_nanos' => 'integer',
            'image_url_expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
