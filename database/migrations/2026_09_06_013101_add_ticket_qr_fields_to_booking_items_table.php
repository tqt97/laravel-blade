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
        Schema::table('booking_items', function (Blueprint $table): void {
            $table->string('qr_token_hash', 64)->nullable()->unique()->after('ticket_code');
            $table->unsignedTinyInteger('qr_payload_version')->default(1)->after('qr_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table): void {
            $table->dropUnique(['qr_token_hash']);
            $table->dropColumn(['qr_token_hash', 'qr_payload_version']);
        });
    }
};
