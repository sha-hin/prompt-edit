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
        Schema::table('generations', function (Blueprint $table): void {
            $table->json('usage')->nullable()->after('rates');           // token counts Pixelforge reports on success
            $table->unsignedBigInteger('cost_nanos')->nullable()->after('usage');    // what Pixelforge charges us
            $table->unsignedBigInteger('charge_nanos')->nullable()->after('cost_nanos');  // what the user pays; 0 when failed
            $table->text('image_url')->nullable()->after('charge_nanos');
            $table->timestamp('image_url_expires_at')->nullable()->after('image_url');
            $table->timestamp('completed_at')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropColumn(['usage', 'cost_nanos', 'charge_nanos', 'image_url', 'image_url_expires_at', 'completed_at']);
        });
    }
};
