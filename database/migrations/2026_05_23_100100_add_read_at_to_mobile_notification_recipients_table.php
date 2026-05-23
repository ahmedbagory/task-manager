<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_notification_recipients', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_notification_recipients', function (Blueprint $table): void {
            $table->dropColumn('read_at');
        });
    }
};
