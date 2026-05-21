<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->json('message_templates')->nullable()->after('graph_version');
        });
    }

    public function down(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->dropColumn('message_templates');
        });
    }
};
