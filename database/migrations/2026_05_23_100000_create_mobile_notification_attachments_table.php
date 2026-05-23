<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_notification_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_notification_id')->constrained('mobile_notifications')->cascadeOnDelete();
            $table->string('disk', 30)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('type', 20)->default('other');
            $table->timestamps();

            $table->index('mobile_notification_id', 'mna_notif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_notification_attachments');
    }
};
