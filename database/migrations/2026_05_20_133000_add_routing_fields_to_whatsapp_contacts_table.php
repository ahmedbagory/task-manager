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
        Schema::table('whatsapp_contacts', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('user_id')->constrained('departments')->nullOnDelete();
            $table->string('default_location')->nullable()->after('department_id');

            $table->index('department_id');
            $table->index('default_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table): void {
            $table->dropIndex(['department_id']);
            $table->dropIndex(['default_location']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('default_location');
        });
    }
};
