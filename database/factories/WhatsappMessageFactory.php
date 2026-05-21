<?php

namespace Database\Factories;

use App\Enums\WhatsappMessageDirection;
use App\Models\Task;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WhatsappMessage>
     */
    protected $model = WhatsappMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whatsapp_message_id' => fake()->optional()->uuid(),
            'contact_id' => fake()->boolean(80) ? WhatsappContact::factory() : null,
            'task_id' => fake()->boolean(35) ? Task::factory() : null,
            'direction' => fake()->randomElement(WhatsappMessageDirection::values()),
            'from_phone' => fake()->optional()->e164PhoneNumber(),
            'to_phone' => fake()->optional()->e164PhoneNumber(),
            'group_id' => fake()->boolean(20) ? fake()->numerify('120363#########@g.us') : null,
            'group_name' => fake()->boolean(20) ? fake()->company().' Group' : null,
            'business_phone_number_id' => fake()->optional()->numerify('###############'),
            'message_type' => fake()->optional()->randomElement(['text', 'image', 'audio', 'document']),
            'body' => fake()->optional()->sentence(),
            'media_url' => fake()->optional()->url(),
            'status' => fake()->optional()->randomElement(['received', 'sent', 'delivered', 'read', 'failed']),
            'raw_payload' => fake()->boolean(70)
                ? [
                    'provider' => 'meta',
                    'channel' => 'whatsapp',
                    'metadata' => [
                        'mock' => true,
                        'event_id' => fake()->uuid(),
                    ],
                ]
                : null,
            'received_at' => fake()->optional()->dateTimeBetween('-7 days', 'now'),
            'sent_at' => fake()->optional()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
