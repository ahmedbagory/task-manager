<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->string('device_id', 120)->nullable()->after('user_id');
            $table->string('device_name', 120)->nullable()->after('device_type');
            $table->timestamp('last_used_at')->nullable()->after('device_name');
        });

        DB::table('device_tokens')
            ->whereNull('device_id')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $token): void {
                DB::table('device_tokens')
                    ->where('id', $token->id)
                    ->update([
                        'device_id' => (string) Str::uuid(),
                        'last_used_at' => now(),
                    ]);
            });

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->unique('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropUnique(['device_id']);
            $table->dropColumn(['device_id', 'device_name', 'last_used_at']);
        });
    }
};
