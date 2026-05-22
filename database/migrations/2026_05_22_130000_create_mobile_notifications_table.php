<?php

use App\Enums\MobileNotificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->string('status', 30)->default(MobileNotificationStatus::QUEUED->value);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->unsignedInteger('targeted_users_count')->default(0);
            $table->unsignedInteger('targeted_users_with_devices_count')->default(0);
            $table->unsignedInteger('targeted_devices_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notifications');
    }
};
