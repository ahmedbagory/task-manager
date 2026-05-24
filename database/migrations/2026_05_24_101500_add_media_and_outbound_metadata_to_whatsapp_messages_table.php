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
            $table->string('media_type')->nullable()->after('media_url');
            $table->string('media_mime')->nullable()->after('media_type');
            $table->string('media_path')->nullable()->after('media_mime');
            $table->string('media_name')->nullable()->after('media_path');
            $table->unsignedBigInteger('media_size')->nullable()->after('media_name');
            $table->boolean('media_rejected')->default(false)->after('media_size');
            $table->text('media_reject_reason')->nullable()->after('media_rejected');
            $table->foreignId('sent_by_user_id')->nullable()->after('task_id')->constrained('users')->nullOnDelete();
            $table->string('external_message_id')->nullable()->after('status');
            $table->text('failed_reason')->nullable()->after('external_message_id');

            $table->index('media_type');
            $table->index('sent_by_user_id');
            $table->index('external_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex(['media_type']);
            $table->dropIndex(['sent_by_user_id']);
            $table->dropIndex(['external_message_id']);
            $table->dropConstrainedForeignId('sent_by_user_id');
            $table->dropColumn([
                'media_type',
                'media_mime',
                'media_path',
                'media_name',
                'media_size',
                'media_rejected',
                'media_reject_reason',
                'external_message_id',
                'failed_reason',
            ]);
        });
    }
};
