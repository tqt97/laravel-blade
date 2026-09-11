<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concession_inventory_movements', function (Blueprint $table): void {
            $table->string('stock_mode', 16)->default('finite')->after('type');
            $table->index('stock_mode');
        });
    }

    public function down(): void
    {
        Schema::table('concession_inventory_movements', function (Blueprint $table): void {
            $table->dropIndex(['stock_mode']);
            $table->dropColumn('stock_mode');
        });
    }
};
