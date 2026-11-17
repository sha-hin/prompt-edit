<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a user's credit ledger. Written only by CreditLedger, never changed after.
 *
 * @property int $id
 * @property int $user_id
 * @property CreditTransactionType $type
 * @property int $amount_nanos
 * @property string|null $generation_id
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'type', 'amount_nanos', 'generation_id', 'idempotency_key'])]
class CreditTransaction extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount_nanos' => 'integer',
        ];
    }
}
