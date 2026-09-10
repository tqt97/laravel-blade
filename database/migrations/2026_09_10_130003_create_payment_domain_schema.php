<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->unique(['payable_type', 'payable_id']);
            $table->string('provider', 32);
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('status', 32)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedBigInteger('amount_minor_units');
            $table->char('currency', 3);
            $table->json('metadata')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['status', 'processing_started_at'], 'payments_processing_lookup_index');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('attempt_key', 128)->unique();
            $table->string('status', 24)->index();
            $table->string('provider_payment_id')->nullable();
            $table->unsignedBigInteger('amount_minor_units');
            $table->char('currency', 3);
            $table->json('metadata')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('attempt_key', 128)->unique();
            $table->string('status', 24)->index();
            $table->string('provider_refund_id')->nullable();
            $table->json('metadata')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 255);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('refund_attempts');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
