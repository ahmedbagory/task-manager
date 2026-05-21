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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->after('phone')->constrained('departments')->nullOnDelete();
            $table->string('work_location')->nullable()->after('department_id');

            $table->unique('phone');
            $table->index('department_id');
            $table->index('work_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['work_location']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['phone', 'work_location']);
        });
    }
};
