<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concessions', function (Blueprint $table): void {
            $table->index(['is_active', 'currency', 'name'], 'concessions_active_currency_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('concessions', function (Blueprint $table): void {
            $table->dropIndex('concessions_active_currency_name_index');
        });
    }
};
