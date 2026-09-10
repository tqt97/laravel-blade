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
        Schema::table('movies', function (Blueprint $table): void {
            $table->string('genre')->nullable()->after('rating');
            $table->string('director')->nullable()->after('genre');
            $table->json('cast')->nullable()->after('director');
            $table->string('language', 32)->nullable()->after('cast');
            $table->string('format', 16)->nullable()->after('language');
            $table->string('backdrop_path')->nullable()->after('poster_path');
            $table->string('trailer_url')->nullable()->after('backdrop_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table): void {
            $table->dropColumn(['genre', 'director', 'cast', 'language', 'format', 'backdrop_path', 'trailer_url']);
        });
    }
};
