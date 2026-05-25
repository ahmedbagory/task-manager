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
        DB::table('tasks')
            ->where('status', TaskStatus::AWAITING_REPORTER_CONFIRMATION->value)
            ->update(['status' => TaskStatus::WAIT_RESPONSE->value]);

        $this->syncTaskStatusEnum(array_values(array_filter(
            TaskStatus::values(),
            static fn (string $value): bool => $value !== TaskStatus::AWAITING_REPORTER_CONFIRMATION->value,
        )));
    }

    /**
     * @param  array<int, string>  $values
     */
    private function syncTaskStatusEnum(array $values): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $quotedValues = implode(', ', array_map(
            static fn (string $value): string => "'{$value}'",
            $values,
        ));

        DB::statement("ALTER TABLE `tasks` MODIFY `status` ENUM({$quotedValues}) NOT NULL DEFAULT '".TaskStatus::NEW->value."'");
    }
};
