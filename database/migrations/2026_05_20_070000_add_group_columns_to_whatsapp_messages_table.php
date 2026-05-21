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
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->string('group_id')->nullable()->after('to_phone');
            $table->string('group_name')->nullable()->after('group_id');

            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex(['group_id']);
            $table->dropColumn(['group_id', 'group_name']);
        });
    }
};
