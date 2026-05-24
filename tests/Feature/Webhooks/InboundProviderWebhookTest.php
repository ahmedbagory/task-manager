<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WhatsappMessageDirection;
use App\Models\ApiSetting;
use App\Models\BridgeStatus;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundProviderWebhookTest extends TestCase
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

    public function test_manual_webhook_stores_inbound_message(): void
    {
        $response = $this->postJson('/webhooks/inbound-message', [
            'from' => '966500000000',
            'to' => 'company',
            'body' => 'AC not working',
            'sender_name' => 'Ahmed',
            'message_type' => 'text',
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $message = WhatsappMessage::query()->first();

        $this->assertNotNull($message);
        $this->assertSame(WhatsappMessageDirection::INBOUND, $message->direction);
        $this->assertSame('966500000000', $message->from_phone);
        $this->assertSame('company', $message->to_phone);
        $this->assertSame('AC not working', $message->body);
        $this->assertNotNull($message->whatsapp_message_id);
        $this->assertNotNull($message->received_at);

        $this->assertDatabaseHas('whatsapp_contacts', [
            'phone' => '966500000000',
            'name' => 'Ahmed',
        ]);
    }

    public function test_twilio_webhook_stores_inbound_message(): void
    {
        $response = $this->post('/webhooks/twilio/whatsapp', [
            'From' => 'whatsapp:+966500000000',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'التكييف لا يعمل',
            'MessageSid' => 'SM123456',
            'ProfileName' => 'Ahmed',
        ]);

        $response->assertOk();
        $response->assertSeeText('OK');

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'SM123456',
            'direction' => WhatsappMessageDirection::INBOUND->value,
            'from_phone' => '+966500000000',
            'to_phone' => '+14155238886',
            'body' => 'التكييف لا يعمل',
            'message_type' => 'text',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('whatsapp_contacts', [
            'phone' => '+966500000000',
            'name' => 'Ahmed',
        ]);
    }

    public function test_meta_verification_returns_challenge(): void
    {
        config(['whatsapp.verify_token' => 'devline_task_manager_2026_verify']);

        $this->get('/webhooks/meta/whatsapp?hub.mode=subscribe&hub.verify_token=devline_task_manager_2026_verify&hub.challenge=123456')
            ->assertOk()
            ->assertContent('123456');
    }

    public function test_meta_duplicate_message_is_not_inserted_twice(): void
    {
        $payload = $this->metaTextPayload(
            messageId: 'wamid.META.DEDUPE.001',
            fromPhone: '201000000111',
            body: 'Leaking pipe',
            timestamp: '1716115999',
            displayPhone: '15551234567',
            phoneNumberId: '998877665544332',
        );

        $this->postJson('/webhooks/meta/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/meta/whatsapp', $payload)->assertOk();

        $this->assertSame(1, WhatsappMessage::query()->where('whatsapp_message_id', 'wamid.META.DEDUPE.001')->count());
        $this->assertSame(1, WhatsappContact::query()->where('phone', '201000000111')->count());
    }

    public function test_bridge_manual_webhook_requires_token_when_secret_configured(): void
    {
        config(['whatsapp.inbound_bridge_secret' => 'bridge-secret']);

        $response = $this->postJson('/webhooks/inbound-message', [
            'provider' => 'whatsapp_web_bridge',
            'from' => '966500000000',
            'to' => '120363000000000000@g.us',
            'group_id' => '120363000000000000@g.us',
            'group_name' => 'Maintenance Group',
            'body' => 'test message',
            'message_type' => 'text',
        ]);

        $response->assertForbidden();
    }

    public function test_bridge_manual_webhook_stores_group_message_and_updates_bridge_status(): void
    {
        config(['whatsapp.inbound_bridge_secret' => 'bridge-secret']);

        $response = $this->withHeader('X-Bridge-Token', 'bridge-secret')
            ->postJson('/webhooks/inbound-message', [
                'provider' => 'whatsapp_web_bridge',
                'provider_message_id' => 'BRIDGE-MSG-001',
                'from' => '966500000000',
                'to' => '120363000000000000@g.us',
                'group_id' => '120363000000000000@g.us',
                'group_name' => 'Maintenance Group',
                'sender_name' => 'Ahmed',
                'body' => 'التكييف لا يعمل',
                'message_type' => 'text',
                'raw_payload' => [
                    'mock' => true,
                ],
            ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'BRIDGE-MSG-001',
            'direction' => WhatsappMessageDirection::INBOUND->value,
            'from_phone' => '966500000000',
            'to_phone' => '120363000000000000@g.us',
            'group_id' => '120363000000000000@g.us',
            'group_name' => 'Maintenance Group',
            'body' => 'التكييف لا يعمل',
        ]);

        $status = BridgeStatus::query()->where('provider', 'whatsapp_web_bridge')->first();

        $this->assertNotNull($status);
        $this->assertSame('connected', $status->status);
        $this->assertSame('120363000000000000@g.us', $status->group_id);
        $this->assertSame('Maintenance Group', $status->group_name);
        $this->assertNotNull($status->last_message_at);
        $this->assertNotNull($status->last_heartbeat_at);
    }

    public function test_bridge_manual_webhook_stores_media_metadata(): void
    {
        config(['whatsapp.inbound_bridge_secret' => 'bridge-secret']);

        $response = $this->withHeader('X-Bridge-Token', 'bridge-secret')
            ->postJson('/webhooks/inbound-message', [
                'provider' => 'whatsapp_web_bridge',
                'provider_message_id' => 'BRIDGE-MEDIA-001',
                'from' => '966500000000',
                'to' => '120363000000000000@g.us',
                'group_id' => '120363000000000000@g.us',
                'group_name' => 'Maintenance Group',
                'sender_name' => 'Ahmed',
                'body' => 'Please review the attached file.',
                'message_type' => 'document',
                'media' => [
                    'has_media' => true,
                    'rejected' => false,
                    'type' => 'document',
                    'mime_type' => 'application/pdf',
                    'file_name' => 'wa_20260524_153000_ab12cd34.pdf',
                    'original_name' => 'invoice.pdf',
                    'size' => 12345,
                    'url' => 'https://task.devline.studio/storage/whatsapp-media/documents/wa_20260524_153000_ab12cd34.pdf',
                    'path' => 'storage/app/public/whatsapp-media/documents/wa_20260524_153000_ab12cd34.pdf',
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'BRIDGE-MEDIA-001',
            'message_type' => 'document',
            'media_type' => 'document',
            'media_mime' => 'application/pdf',
            'media_path' => 'storage/app/public/whatsapp-media/documents/wa_20260524_153000_ab12cd34.pdf',
            'media_url' => 'https://task.devline.studio/storage/whatsapp-media/documents/wa_20260524_153000_ab12cd34.pdf',
            'media_name' => 'invoice.pdf',
            'media_size' => 12345,
            'media_rejected' => false,
        ]);
    }

    public function test_bridge_manual_webhook_stores_rejected_media_reason(): void
    {
        config(['whatsapp.inbound_bridge_secret' => 'bridge-secret']);

        $response = $this->withHeader('X-Bridge-Token', 'bridge-secret')
            ->postJson('/webhooks/inbound-message', [
                'provider' => 'whatsapp_web_bridge',
                'provider_message_id' => 'BRIDGE-MEDIA-REJECT-001',
                'from' => '966500000000',
                'to' => '120363000000000000@g.us',
                'group_id' => '120363000000000000@g.us',
                'group_name' => 'Maintenance Group',
                'body' => 'Attachment was blocked.',
                'message_type' => 'document',
                'media' => [
                    'has_media' => true,
                    'rejected' => true,
                    'type' => 'document',
                    'mime_type' => 'application/x-msdownload',
                    'original_name' => 'script.exe',
                    'size' => 2048,
                    'reason' => 'Rejected media MIME type: application/x-msdownload',
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'BRIDGE-MEDIA-REJECT-001',
            'media_type' => 'document',
            'media_mime' => 'application/x-msdownload',
            'media_name' => 'script.exe',
            'media_size' => 2048,
            'media_rejected' => true,
            'media_reject_reason' => 'Rejected media MIME type: application/x-msdownload',
        ]);
    }

    public function test_bridge_heartbeat_updates_bridge_status_record(): void
    {
        config(['whatsapp.inbound_bridge_secret' => 'bridge-secret']);

        $response = $this->withHeader('X-Bridge-Token', 'bridge-secret')
            ->postJson('/webhooks/bridge/heartbeat', [
                'provider' => 'whatsapp_web_bridge',
                'status' => 'connected',
                'account_id' => '966500000000',
                'account_name' => 'Company WhatsApp',
                'group_id' => '120363000000000000@g.us',
                'group_name' => 'Maintenance Group',
                'meta' => [
                    'groups_count' => 3,
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $status = BridgeStatus::query()->where('provider', 'whatsapp_web_bridge')->first();

        $this->assertNotNull($status);
        $this->assertSame('connected', $status->status);
        $this->assertSame('966500000000', $status->account_id);
        $this->assertSame('Company WhatsApp', $status->account_name);
        $this->assertSame('120363000000000000@g.us', $status->group_id);
        $this->assertSame('Maintenance Group', $status->group_name);
        $this->assertNotNull($status->last_heartbeat_at);
        $this->assertSame(3, data_get($status->meta, 'groups_count'));
    }

    public function test_enforce_selected_provider_ignores_non_selected_provider_payloads(): void
    {
        ApiSetting::query()->create([
            'provider' => 'twilio',
            'enforce_selected_provider' => true,
            'outbound_enabled' => false,
        ]);

        $response = $this->postJson('/webhooks/inbound-message', [
            'provider' => 'manual',
            'from' => '966500000000',
            'to' => 'company',
            'body' => 'Ignored payload',
            'message_type' => 'text',
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'status' => 'ignored',
            'reason' => 'provider_not_active',
        ]);

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_enforce_selected_provider_accepts_selected_provider_payloads(): void
    {
        ApiSetting::query()->create([
            'provider' => 'twilio',
            'enforce_selected_provider' => true,
            'outbound_enabled' => false,
        ]);

        $response = $this->post('/webhooks/twilio/whatsapp', [
            'From' => 'whatsapp:+966500000123',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'Allowed payload',
            'MessageSid' => 'SM_ENFORCED_001',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_message_id' => 'SM_ENFORCED_001',
            'from_phone' => '+966500000123',
            'body' => 'Allowed payload',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metaTextPayload(
        string $messageId,
        string $fromPhone,
        string $body,
        string $timestamp,
        string $displayPhone,
        string $phoneNumberId,
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
}
