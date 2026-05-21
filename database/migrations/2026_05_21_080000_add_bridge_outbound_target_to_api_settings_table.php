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
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->string('bridge_outbound_target')->default('direct_phone')->after('outbound_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->dropColumn('bridge_outbound_target');
        });
    }
};
