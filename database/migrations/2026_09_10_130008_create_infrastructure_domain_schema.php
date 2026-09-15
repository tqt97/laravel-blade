<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('aggregate_type', 128);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 128);
            $table->json('payload');
            $table->timestamp('available_at')->useCurrent()
                ->comment('Earliest time the outbox publisher may claim this message.');
            $table->timestamp('claimed_at')->nullable()
                ->comment('Timestamp when a publisher worker claimed the message.');
            $table->timestamp('published_at')->nullable()
                ->comment('Timestamp when the message was successfully published.');
            $table->unsignedInteger('attempts')->default(0)
                ->comment('Number of outbox publish attempts.');
            $table->timestamp('failed_at')->nullable()
                ->comment('Terminal failure timestamp; non-null messages require retry or review.');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'failed_at', 'claimed_at', 'available_at'], 'outbox_claimable_index');
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        Schema::create('outbox_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outbox_message_id')->constrained('outbox_messages')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('idempotency_key', 191)->nullable()->unique()
                ->comment('Stable delivery key preventing duplicate external sends on retries.');
            $table->string('status', 24)->index();
            $table->unsignedInteger('attempts')->default(0)
                ->comment('Number of delivery attempts for this channel.');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id', 255)->nullable()
                ->comment('External provider message id returned after successful delivery.');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['outbox_message_id', 'channel']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('outbox_deliveries');
        Schema::dropIfExists('outbox_messages');
    }
};
