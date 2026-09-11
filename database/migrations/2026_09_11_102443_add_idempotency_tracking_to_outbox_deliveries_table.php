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
        Schema::table('outbox_deliveries', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->unique()->after('channel');
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->string('message_id', 255)->nullable()->after('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outbox_deliveries', function (Blueprint $table) {
            $table->dropUnique('outbox_deliveries_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'attempts', 'message_id']);
        });
    }
};
