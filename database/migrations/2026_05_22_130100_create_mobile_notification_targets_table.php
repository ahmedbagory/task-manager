<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
};
