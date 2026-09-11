<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider_status', 48)->nullable()->after('provider_payment_id');
            $table->json('provider_metadata')->nullable()->after('metadata');
            $table->text('client_secret')->nullable()->after('provider_metadata');
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('payment_method_reference')->nullable()->after('provider_payment_id');
            $table->json('request_metadata')->nullable()->after('metadata');
            $table->json('response_metadata')->nullable()->after('request_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropColumn(['payment_method_reference', 'request_metadata', 'response_metadata']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['provider_status', 'provider_metadata', 'client_secret']);
        });
    }
};
