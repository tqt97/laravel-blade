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
            $table->string('provider_payment_id')->nullable()->unique()
                ->comment('Stripe PaymentIntent id; NULL before the provider returns an id.');
            $table->string('provider_status', 48)->nullable()
                ->comment('Raw StripePaymentIntentStatus; separate from internal PaymentStatus.');
            $table->string('status', 32)->index()
                ->comment('PaymentStatus enum controlling internal payment transitions.');
            $table->unsignedInteger('attempts')->default(0)
                ->comment('Number of payment processing attempts, including retries.');
            $table->unsignedBigInteger('amount_minor_units')
                ->comment('Amount sent to the provider in minor currency units.');
            $table->char('currency', 3)
                ->comment('ISO 4217 currency code for the provider amount.');
            $table->json('metadata')->nullable();
            $table->json('provider_metadata')->nullable()
                ->comment('Raw provider attributes for support/reconciliation, not business truth.');
            $table->text('client_secret')->nullable()
                ->comment('Stripe.js confirmation secret; sensitive, never expose from admin APIs.');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('reconciliation_attempted_at')->nullable()
                ->comment('Timestamp of the last provider reconciliation attempt.');
            $table->unsignedInteger('reconciliation_attempts')->default(0)
                ->comment('Number of reconciliation attempts after an uncertain response.');
            $table->timestamp('next_reconcile_at')->nullable()
                ->comment('Next scheduled time to query the provider authoritatively.');
            $table->timestamp('reconciliation_deadline')->nullable()
                ->comment('After this deadline an unresolved payment requires manual review.');
            $table->text('last_reconciliation_error')->nullable()
                ->comment('Last safe-to-log reconciliation failure message.');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['status', 'processing_started_at'], 'payments_processing_lookup_index');
            $table->index(['status', 'reconciliation_attempted_at'], 'payments_reconciliation_lookup_index');
            // Scheduler query for payments due for provider reconciliation.
            $table->index(['status', 'next_reconcile_at'], 'payments_reconciliation_schedule_index');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('attempt_key', 128)->unique()
                ->comment('Immutable logical attempt key and provider idempotency key.');
            $table->string('status', 24)->index()
                ->comment('PaymentAttemptStatus enum: processing, pending, succeeded, failed, requires_action, requires_payment_method or unknown.');
            $table->string('provider_payment_id')->nullable();
            $table->string('payment_method_reference')->nullable();
            $table->unsignedBigInteger('amount_minor_units');
            $table->char('currency', 3);
            $table->json('metadata')->nullable();
            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();
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
            $table->string('provider_refund_id')->nullable()
                ->comment('Stripe Refund id; populated only after Stripe accepts the request.');
            $table->json('metadata')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('reconciliation_attempts')->default(0)
                ->comment('Number of refund status reconciliation attempts.');
            $table->timestamp('next_reconcile_at')->nullable()
                ->comment('Next scheduled provider refund status check.');
            $table->timestamps();

            $table->index(['status', 'next_reconcile_at'], 'refund_attempts_reconciliation_index');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 255)
                ->comment('Stripe event id used for webhook idempotency.');
            $table->string('provider_payment_id')->nullable()
                ->comment('Provider object id used to attach delayed or orphan events.');
            $table->string('provider_object_type', 32)->nullable()
                ->comment('Provider object type, for example payment_intent or refund.');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('orphaned_at')->nullable()
                ->comment('Set when the event cannot yet be matched to a local payment or refund.');
            $table->unsignedSmallInteger('processing_attempts')->default(0)
                ->comment('Number of webhook processing attempts, including delayed retries.');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'provider_payment_id'], 'payment_webhook_provider_payment_index');
            $table->index(['provider', 'provider_object_type', 'provider_payment_id'], 'payment_webhook_object_lookup_index');
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
