<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WhatsappContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappContact>
 */
class WhatsappContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WhatsappContact>
     */
    protected $model = WhatsappContact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => fake()->unique()->e164PhoneNumber(),
            'name' => fake()->optional()->name(),
            'user_id' => fake()->boolean(30) ? User::factory() : null,
            'department_id' => null,
            'default_location' => fake()->optional()->city(),
            'last_message_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
