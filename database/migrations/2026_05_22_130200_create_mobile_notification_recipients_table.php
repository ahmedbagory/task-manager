<?php

use App\Enums\MobileNotificationRecipientStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_notification_recipients')) {
            Schema::table('mobile_notification_recipients', function (Blueprint $table): void {
                if (! $this->indexExists('mobile_notification_recipients', 'mnr_notif_user_unq')) {
                    $table->unique(['mobile_notification_id', 'user_id'], 'mnr_notif_user_unq');
                }

                if (! $this->indexExists('mobile_notification_recipients', 'mnr_notif_status_idx')) {
                    $table->index(['mobile_notification_id', 'status'], 'mnr_notif_status_idx');
                }
            });

            return;
        }

        Schema::create('mobile_notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_notification_id')->constrained('mobile_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default(MobileNotificationRecipientStatus::PENDING->value);
            $table->unsignedInteger('device_count')->default(0);
            $table->unsignedInteger('delivered_devices_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['mobile_notification_id', 'user_id'], 'mnr_notif_user_unq');
            $table->index(['mobile_notification_id', 'status'], 'mnr_notif_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notification_recipients');
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
