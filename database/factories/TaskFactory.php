<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Task>
     */
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'task_number' => fake()->unique()->numerify('TSK-######'),
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'department_id' => fake()->boolean(80) ? Department::factory() : null,
            'category_id' => fake()->boolean(80) ? TaskCategory::factory() : null,
            'reported_by_user_id' => fake()->boolean(50) ? User::factory() : null,
            'reported_by_phone' => fake()->optional()->e164PhoneNumber(),
            'assigned_to_user_id' => fake()->boolean(60) ? User::factory() : null,
            'priority' => fake()->randomElement(TaskPriority::values()),
            'status' => fake()->randomElement(TaskStatus::values()),
            'source' => fake()->randomElement(TaskSource::values()),
            'location' => fake()->optional()->address(),
            'due_at' => fake()->optional()->dateTimeBetween('now', '+14 days'),
            'started_at' => fake()->optional()->dateTimeBetween($createdAt, 'now'),
            'completed_at' => fake()->optional(0.3)->dateTimeBetween('now', '+5 days'),
            'created_by' => fake()->boolean(60) ? User::factory() : null,
            'updated_by' => fake()->boolean(60) ? User::factory() : null,
            'created_at' => $createdAt,
            'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
        ];
    }
}
