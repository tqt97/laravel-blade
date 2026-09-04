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
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['deleted_at', 'is_admin', 'id'], 'users_deleted_admin_id_index');
            $table->index(['deleted_at', 'email_verified_at', 'id'], 'users_deleted_verified_id_index');
            $table->index(['deleted_at', 'two_factor_confirmed_at', 'id'], 'users_deleted_2fa_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deleted_admin_id_index');
            $table->dropIndex('users_deleted_verified_id_index');
            $table->dropIndex('users_deleted_2fa_id_index');
        });
    }
};
