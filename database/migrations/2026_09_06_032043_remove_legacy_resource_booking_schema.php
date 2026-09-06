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
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'resource_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropForeign(['resource_id']);
                $table->dropIndex('bookings_resource_id_status_start_at_end_at_index');
                $table->dropColumn(['resource_id', 'start_at', 'end_at']);
            });
        }

        Schema::dropIfExists('bookable_resources');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('bookable_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('operating_hours')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('capacity')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('resource_id')->nullable()->constrained('bookable_resources')->nullOnDelete();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->index(['resource_id', 'status', 'start_at', 'end_at']);
        });
    }
};
