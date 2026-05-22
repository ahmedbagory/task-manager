<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('task_assignment_targets')
            ->whereIn('target_type', ['branch', 'company_category'])
            ->update(['target_type' => 'department']);
    }

    public function down(): void
    {
        // Historical target types are intentionally not restored.
    }
};
