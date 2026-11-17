<?php

use App\Credits\CreditLedger;
use App\Enums\CreditTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The ledger's running total, kept in the same transaction as each entry. Signed: a charge can
            // land just past the estimate when Pixelforge changes its rates, and is never refused.
            $table->bigInteger('balance_nanos')->default(0)->after('is_admin');
        });

        Schema::create('credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->bigInteger('amount_nanos');                             // + adds credit, - spends it
            $table->foreignUlid('generation_id')->nullable()->unique()->constrained()->nullOnDelete();  // one charge per generation, ever
            $table->string('idempotency_key')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'idempotency_key']);                 // a retried purchase or grant is a no-op
        });

        // Users who signed up before credits existed get the same free credit as new ones.
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('credit_transactions')->insert([
                'user_id' => $user->id,
                'type' => CreditTransactionType::Grant->value,
                'amount_nanos' => CreditLedger::SIGNUP_BONUS_NANOS,
                'idempotency_key' => CreditLedger::SIGNUP_BONUS_KEY,
                'created_at' => now(),
            ]);
            DB::table('users')->where('id', $user->id)->update(['balance_nanos' => CreditLedger::SIGNUP_BONUS_NANOS]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('balance_nanos');
        });
    }
};
