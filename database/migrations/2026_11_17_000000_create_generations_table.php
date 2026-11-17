<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key')->unique();                 // sent on every create attempt, so retries never duplicate
            $table->string('pixelforge_id')->nullable()->unique();
            $table->text('prompt');
            $table->string('size', 16);
            $table->string('quality', 16);
            $table->json('reference_images');
            $table->string('status', 16)->index();
            $table->unsignedBigInteger('estimate_min_nanos');           // nano-dollars (1e-9 USD)
            $table->unsignedBigInteger('estimate_max_nanos');
            $table->json('rates')->nullable();                          // locked by Pixelforge on create, stored as sent
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
