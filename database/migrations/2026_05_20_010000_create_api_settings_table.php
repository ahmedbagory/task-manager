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
        Schema::create('api_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('meta');
            $table->boolean('outbound_enabled')->default(false);
            $table->text('verify_token')->nullable();
            $table->text('access_token')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('api_base_url')->default('https://graph.facebook.com');
            $table->string('graph_version')->default('v23.0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_settings');
    }
};
