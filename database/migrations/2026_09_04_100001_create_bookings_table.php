<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resource_id')->constrained('bookable_resources')->restrictOnDelete();
            $table->string('status', 32)->index();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('expires_at')->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->string('idempotency_hash', 64)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['resource_id', 'status', 'start_at', 'end_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
