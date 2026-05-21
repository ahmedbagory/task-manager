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
            ->where('status', TaskStatus::ACCEPTED->value)
            ->update(['status' => TaskStatus::ASSIGNED->value]);

        DB::table('tasks')
            ->where('status', TaskStatus::WAIT_RESPONSE->value)
            ->update(['status' => TaskStatus::IN_PROGRESS->value]);

        $this->syncTaskStatusEnum([
            TaskStatus::NEW->value,
            TaskStatus::PENDING_ASSIGNMENT->value,
            TaskStatus::ASSIGNED->value,
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::COMPLETED->value,
            TaskStatus::CANCELLED->value,
            TaskStatus::REJECTED->value,
        ]);
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
