<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAttachment>
 */
class TaskAttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TaskAttachment>
     */
    protected $model = TaskAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'jpeg', 'png', 'pdf', 'docx']);

        return [
            'task_id' => Task::factory(),
            'user_id' => fake()->boolean(75) ? User::factory() : null,
            'disk' => fake()->randomElement(['local', 'public']),
            'path' => 'tasks/'.fake()->uuid().'.'.$extension,
            'original_name' => fake()->optional()->word().'.'.$extension,
            'mime_type' => fake()->optional()->mimeType(),
            'size' => fake()->optional()->numberBetween(1024, 5_000_000),
            'type' => fake()->optional()->randomElement(['photo', 'document', 'audio', 'video']),
        ];
    }
}
