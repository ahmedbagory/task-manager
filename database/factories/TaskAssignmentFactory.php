<?php

namespace Database\Factories;

use App\Enums\TaskAssignmentStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TaskAssignment>
     */
    protected $model = TaskAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(TaskAssignmentStatus::values());
        $assignedAt = fake()->dateTimeBetween('-14 days', 'now');

        $acceptedAt = null;
        $completedAt = null;

        if (in_array($status, [TaskAssignmentStatus::ACCEPTED->value, TaskAssignmentStatus::COMPLETED->value], true)) {
            $acceptedAt = fake()->dateTimeBetween($assignedAt, 'now');
        }

        if ($status === TaskAssignmentStatus::COMPLETED->value) {
            $completedAt = fake()->dateTimeBetween($acceptedAt ?? $assignedAt, 'now');
        }

        return [
            'task_id' => Task::factory(),
            'assigned_to_user_id' => User::factory(),
            'assigned_by_user_id' => fake()->boolean(70) ? User::factory() : null,
            'note' => fake()->optional()->sentence(),
            'status' => $status,
            'assigned_at' => $assignedAt,
            'accepted_at' => $acceptedAt,
            'completed_at' => $completedAt,
        ];
    }
}
