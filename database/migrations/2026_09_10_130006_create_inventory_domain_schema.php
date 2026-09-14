<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concession_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concession_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24)->index()
                ->comment('InventoryMovementType enum: initial, adjustment, reserve, release or refund.');
            $table->string('stock_mode', 16)->default('finite')->index()
                ->comment('InventoryStockMode enum: finite or unlimited.');
            $table->integer('quantity_delta')
                ->comment('Signed stock change; reserve is negative and release/refund is positive.');
            $table->unsignedInteger('stock_before')->nullable()
                ->comment('Balance before movement; NULL for unlimited stock.');
            $table->unsignedInteger('stock_after')->nullable()
                ->comment('Balance after movement; NULL for unlimited stock.');
            $table->string('reference', 128)->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique()
                ->comment('Stable operation key preventing duplicate stock movements on retries.');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['concession_id', 'created_at']);
        });

        Schema::create('concession_stock_adjustment_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concession_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->unsignedInteger('stock_before')->nullable();
            $table->unsignedInteger('stock_after')->nullable();
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concession_stock_adjustment_audits');
        Schema::dropIfExists('concession_inventory_movements');
    }
};
