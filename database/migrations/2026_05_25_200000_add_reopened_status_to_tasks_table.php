<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncTaskStatusEnum(TaskStatus::values());
    }

    public function down(): void
    {
        $this->syncTaskStatusEnum(array_values(array_filter(
            TaskStatus::values(),
            fn (string $value): bool => $value !== 'reopened',
        )));
    }

    private function syncTaskStatusEnum(array $values): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $quotedValues = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            $values,
        ));

        DB::statement("ALTER TABLE `tasks` MODIFY `status` ENUM({$quotedValues}) NOT NULL DEFAULT '".TaskStatus::NEW->value."'");
    }
};
