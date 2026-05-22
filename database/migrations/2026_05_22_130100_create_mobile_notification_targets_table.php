<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_notification_targets')) {
            Schema::table('mobile_notification_targets', function (Blueprint $table): void {
                if (! $this->indexExists('mobile_notification_targets', 'mnt_notif_type_idx')) {
                    $table->index(['mobile_notification_id', 'target_type'], 'mnt_notif_type_idx');
                }

                if (! $this->indexExists('mobile_notification_targets', 'mnt_type_id_idx')) {
                    $table->index(['target_type', 'target_id'], 'mnt_type_id_idx');
                }

                if (! $this->indexExists('mobile_notification_targets', 'mnt_notif_type_target_unq')) {
                    $table->unique(['mobile_notification_id', 'target_type', 'target_id'], 'mnt_notif_type_target_unq');
                }
            });

            return;
        }

        Schema::create('mobile_notification_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_notification_id')->constrained('mobile_notifications')->cascadeOnDelete();
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->index(['mobile_notification_id', 'target_type'], 'mnt_notif_type_idx');
            $table->index(['target_type', 'target_id'], 'mnt_type_id_idx');
            $table->unique(['mobile_notification_id', 'target_type', 'target_id'], 'mnt_notif_type_target_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notification_targets');
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
