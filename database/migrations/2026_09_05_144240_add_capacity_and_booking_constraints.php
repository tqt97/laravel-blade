<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookable_resources', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->default(1)->after('is_active');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE bookable_resources ADD CONSTRAINT bookable_resources_capacity_positive CHECK (capacity > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE bookable_resources DROP CHECK bookable_resources_capacity_positive');
        }

        Schema::table('bookable_resources', function (Blueprint $table): void {
            $table->dropColumn('capacity');
        });
    }
};
