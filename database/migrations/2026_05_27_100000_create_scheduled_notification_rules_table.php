<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general');
            $table->string('target_type')->default('all');
            $table->json('target_payload')->nullable();
            $table->string('frequency')->default('daily');
            $table->unsignedSmallInteger('interval_hours')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });

        Schema::create('scheduled_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('scheduled_notification_rules')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->string('status')->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_notification_logs');
        Schema::dropIfExists('scheduled_notification_rules');
    }
};
