<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concessions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku', 64)->unique();
            $table->unsignedBigInteger('price_minor_units')
                ->comment('Selling price in minor currency units; never store floating-point money.');
            $table->char('currency', 3)
                ->comment('ISO 4217 currency code matching price_minor_units.');
            $table->unsignedInteger('stock')->nullable()
                ->comment('NULL means unlimited stock; zero or positive means finite stock.');
            $table->boolean('is_active')->default(true)->index();
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'currency', 'name'], 'concessions_active_currency_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concessions');
    }
};
