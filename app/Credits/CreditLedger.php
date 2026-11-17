<?php

namespace App\Credits;

use App\Enums\CreditPack;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use App\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * The only code that moves credit. Every entry and the balance it changes are written in one
 * transaction under a lock on the user row, so users.balance_nanos always equals the ledger sum.
 */
final readonly class CreditLedger
{
    public const int SIGNUP_BONUS_NANOS = 10 * Money::NANOS_PER_DOLLAR;     // $10 to try the app

    public const string SIGNUP_BONUS_KEY = 'signup-bonus';                 // one per user, via unique(user_id, idempotency_key)

    public function grantSignupBonus(User $user): CreditTransaction
    {
        return $this->grant($user, Money::nanos(self::SIGNUP_BONUS_NANOS), self::SIGNUP_BONUS_KEY);
    }

    /**
     * Free credit. A key already used for this user returns the first entry and adds nothing.
     */
    public function grant(User $user, Money $amount, string $idempotencyKey): CreditTransaction
    {
        return $this->record($user, ['user_id' => $user->id, 'idempotency_key' => $idempotencyKey], [
            'type' => CreditTransactionType::Grant,
            'amount_nanos' => $amount->nanos,
        ]);
    }

    /**
     * A fake purchase. A repeated key (a double click or a retry) returns the first purchase and adds nothing.
     */
    public function purchase(User $user, CreditPack $pack, string $idempotencyKey): CreditTransaction
    {
        return $this->record($user, ['user_id' => $user->id, 'idempotency_key' => $idempotencyKey], [
            'type' => CreditTransactionType::Purchase,
            'amount_nanos' => $pack->money()->nanos,
        ]);
    }

    /**
     * Charge a succeeded generation, at most once ever (generation_id is unique). Called inside
     * ApplyGenerationUpdate's transaction, so the charge and the final status commit together.
     * Never refused: the image already exists, so a balance can end just below zero.
     */
    public function charge(Generation $generation): void
    {
        if ($generation->user_id === null || $generation->charge_nanos === null) {
            return;                                         // a deleted account, or nothing to charge
        }

        $user = $generation->user()->firstOrFail();

        $this->record($user, ['generation_id' => $generation->id], [
            'user_id' => $user->id,
            'type' => CreditTransactionType::Charge,
            'amount_nanos' => -$generation->charge_nanos,
        ]);
    }

    /**
     * The most that generations still running could be charged: their estimate maximum.
     */
    public function reserved(User $user): Money
    {
        return Money::nanos((int) $user->generations()
            ->whereIn('status', GenerationStatus::inFlight())
            ->sum('estimate_max_nanos'));
    }

    /**
     * What the user can still spend: the balance less what running generations may cost.
     */
    public function available(User $user): Money
    {
        return Money::nanos($user->balance_nanos)->subtract($this->reserved($user));
    }

    /**
     * Lock the user's row and check they can afford $amount. The caller's transaction holds the lock
     * until it commits, so a second submission waits and then sees the first one's reservation.
     *
     * @throws InsufficientCredits
     */
    public function lockAndEnsureCanAfford(User $user, Money $amount): User
    {
        $locked = User::query()->lockForUpdate()->findOrFail($user->id);
        $available = $this->available($locked);

        if ($available->nanos < $amount->nanos) {
            throw new InsufficientCredits($amount, $available);
        }

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $unique  the columns that make this entry unique
     * @param  array<string, mixed>  $values
     */
    private function record(User $user, array $unique, array $values): CreditTransaction
    {
        return DB::transaction(function () use ($user, $unique, $values): CreditTransaction {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();    // one entry at a time per user

            $entry = CreditTransaction::query()->createOrFirst($unique, $values);

            if ($entry->wasRecentlyCreated) {
                User::query()->whereKey($user->id)->increment('balance_nanos', $entry->amount_nanos);
            }

            $user->balance_nanos = (int) User::query()->whereKey($user->id)->value('balance_nanos');   // keep the caller's model current

            return $entry;
        });
    }
}
