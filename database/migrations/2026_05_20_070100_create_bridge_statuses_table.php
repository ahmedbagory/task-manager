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
        Schema::create('bridge_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->index();
            $table->string('status')->nullable();
            $table->string('account_id')->nullable();
            $table->string('account_name')->nullable();
            $table->string('group_id')->nullable()->index();
            $table->string('group_name')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bridge_statuses');
    }
};
