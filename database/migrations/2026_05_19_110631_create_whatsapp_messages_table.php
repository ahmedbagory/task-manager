<?php

use App\Enums\WhatsappMessageDirection;
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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_message_id')->nullable()->unique();
            $table->foreignId('contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', WhatsappMessageDirection::values());
            $table->string('from_phone')->nullable();
            $table->string('to_phone')->nullable();
            $table->string('message_type')->nullable();
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->string('status')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('contact_id');
            $table->index('task_id');
            $table->index('direction');
            $table->index('status');
            $table->index('received_at');
            $table->index('sent_at');
            $table->index('from_phone');
            $table->index('to_phone');
            $table->index(['direction', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
