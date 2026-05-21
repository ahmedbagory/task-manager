<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\TaskCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskCategory>
 */
class TaskCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TaskCategory>
     */
    protected $model = TaskCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('CAT???')),
            'description' => fake()->optional()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }

    public function withoutDepartment(): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => null,
        ]);
    }
}
