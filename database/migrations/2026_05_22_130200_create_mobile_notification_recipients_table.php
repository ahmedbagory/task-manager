<?php

use App\Enums\MobileNotificationRecipientStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_notification_id')->constrained('mobile_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default(MobileNotificationRecipientStatus::PENDING->value);
            $table->unsignedInteger('device_count')->default(0);
            $table->unsignedInteger('delivered_devices_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['mobile_notification_id', 'user_id']);
            $table->index(['mobile_notification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notification_recipients');
    }
};
