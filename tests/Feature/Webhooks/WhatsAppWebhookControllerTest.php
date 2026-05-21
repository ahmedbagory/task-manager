<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WhatsappMessageDirection;
use App\Models\ApiSetting;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.provider' => 'meta',
            'whatsapp.enforce_selected_provider' => false,
        ]);
    }

    public function test_verify_returns_challenge_for_valid_token(): void
    {
        config(['whatsapp.verify_token' => 'verify-secret']);

        $response = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-secret&hub.challenge=12345');

        $response->assertOk();
        $response->assertContent('12345');
    }

    public function test_verify_returns_forbidden_for_invalid_token(): void
    {
        config(['whatsapp.verify_token' => 'verify-secret']);

        $response = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong-token&hub.challenge=12345');

        $response->assertForbidden();
    }

    public function test_verify_uses_database_token_override_when_available(): void
    {
        config(['whatsapp.verify_token' => 'verify-from-config']);

        ApiSetting::query()->create([
            'provider' => 'meta',
            'outbound_enabled' => false,
            'verify_token' => 'verify-from-db',
            'api_base_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
        ]);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-from-db&hub.challenge=12345')
            ->assertOk()
            ->assertContent('12345');

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-from-config&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_receive_stores_inbound_text_message_and_contact(): void
    {
        $payload = $this->textMessagePayload(
            messageId: 'wamid.TEXT.001',
            fromPhone: '201000000001',
            body: 'Leaking pipe in floor 2',
            timestamp: '1716115077',
            displayPhone: '15551234567',
            phoneNumberId: '998877665544332'
        );

        $this->postJson('/webhooks/whatsapp', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('whatsapp_contacts', [
            'phone' => '201000000001',
            'name' => 'Facility Reporter',
        ]);

        $message = WhatsappMessage::query()->first();

        $this->assertNotNull($message);
        $this->assertSame('wamid.TEXT.001', $message->whatsapp_message_id);
        $this->assertSame(WhatsappMessageDirection::INBOUND, $message->direction);
        $this->assertSame('text', $message->message_type);
        $this->assertSame('Leaking pipe in floor 2', $message->body);
        $this->assertSame('15551234567', $message->to_phone);
        $this->assertSame('998877665544332', $message->business_phone_number_id);
        $this->assertNotNull($message->received_at);
        $this->assertSame(1716115077, $message->received_at?->utc()->timestamp);
        $this->assertNotNull($message->raw_payload);
    }

    public function test_receive_prevents_duplicate_messages_by_whatsapp_message_id(): void
    {
        $payload = $this->textMessagePayload(
            messageId: 'wamid.TEXT.DEDUPE',
            fromPhone: '201000000002',
            body: 'Power outage in office 3',
            timestamp: '1716115078',
            displayPhone: '15551234567',
            phoneNumberId: '998877665544332'
        );

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $this->assertSame(1, WhatsappMessage::query()->where('whatsapp_message_id', 'wamid.TEXT.DEDUPE')->count());
        $this->assertSame(1, WhatsappContact::query()->where('phone', '201000000002')->count());
    }

    public function test_receive_accepts_unknown_message_type_and_stores_raw_payload(): void
    {
        $payload = $this->unknownMessageTypePayload();

        $this->postJson('/webhooks/whatsapp', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $message = WhatsappMessage::query()->first();

        $this->assertNotNull($message);
        $this->assertSame('reaction', $message->message_type);
        $this->assertNull($message->body);
        $this->assertNotNull($message->raw_payload);
        $this->assertSame('wamid.UNKNOWN.001', data_get($message->raw_payload, 'message.id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function textMessagePayload(
        string $messageId,
        string $fromPhone,
        string $body,
        string $timestamp,
        string $displayPhone,
        string $phoneNumberId
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => $displayPhone,
                                    'phone_number_id' => $phoneNumberId,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Facility Reporter'],
                                        'wa_id' => $fromPhone,
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => $fromPhone,
                                        'id' => $messageId,
                                        'timestamp' => $timestamp,
                                        'type' => 'text',
                                        'text' => [
                                            'body' => $body,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unknownMessageTypePayload(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '1234567890',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'metadata' => [
                                    'display_phone_number' => '15551234567',
                                    'phone_number_id' => '998877665544332',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Forwarder'],
                                        'wa_id' => '201000000003',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '201000000003',
                                        'id' => 'wamid.UNKNOWN.001',
                                        'timestamp' => '1716115079',
                                        'type' => 'reaction',
                                        'reaction' => [
                                            'message_id' => 'wamid.OTHER.001',
                                            'emoji' => '?',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
