<?php

namespace Tests\Feature\Services;

use App\Enums\WhatsappMessageDirection;
use App\Models\ApiSetting;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_text_message_does_not_call_meta_when_outbound_is_disabled(): void
    {
        config([
            'whatsapp.outbound_enabled' => false,
            'whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake();

        $result = app(WhatsAppClient::class)->sendTextMessage('+201000000001', 'Test outbound message');

        Http::assertNothingSent();

        $this->assertFalse($result->sent);
        $this->assertSame('pending_disabled', $result->status);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $result->messageRecord->id,
            'direction' => WhatsappMessageDirection::OUTBOUND->value,
            'to_phone' => '+201000000001',
            'message_type' => 'text',
            'status' => 'pending_disabled',
            'sent_at' => null,
        ]);
    }

    public function test_send_text_message_calls_meta_and_stores_sent_message_on_success(): void
    {
        config([
            'whatsapp.outbound_enabled' => true,
            'whatsapp.access_token' => 'test-access-token',
            'whatsapp.phone_number_id' => '999888777',
            'whatsapp.api_base_url' => 'https://graph.facebook.com',
            'whatsapp.graph_version' => 'v23.0',
        ]);

        Http::fake([
            'https://graph.facebook.com/v23.0/999888777/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '+201000000002', 'wa_id' => '201000000002']],
                'messages' => [['id' => 'wamid.HBgMTEST123456']],
            ], 200),
        ]);

        $result = app(WhatsAppClient::class)->sendTextMessage('+201000000002', 'Task update message');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v23.0/999888777/messages'
                && $request['to'] === '+201000000002'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Task update message';
        });

        $this->assertTrue($result->sent);
        $this->assertSame('sent', $result->status);
        $this->assertSame('wamid.HBgMTEST123456', $result->whatsappMessageId);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $result->messageRecord->id,
            'direction' => WhatsappMessageDirection::OUTBOUND->value,
            'to_phone' => '+201000000002',
            'status' => 'sent',
            'whatsapp_message_id' => 'wamid.HBgMTEST123456',
        ]);

        $this->assertNotNull($result->messageRecord->fresh()->sent_at);
    }

    public function test_send_text_message_marks_record_failed_when_meta_returns_error(): void
    {
        config([
            'whatsapp.outbound_enabled' => true,
            'whatsapp.access_token' => 'test-access-token',
            'whatsapp.phone_number_id' => '999888777',
            'whatsapp.api_base_url' => 'https://graph.facebook.com',
            'whatsapp.graph_version' => 'v23.0',
        ]);

        Http::fake([
            'https://graph.facebook.com/v23.0/999888777/messages' => Http::response([
                'error' => [
                    'message' => 'Invalid recipient',
                    'type' => 'OAuthException',
                ],
            ], 400),
        ]);

        $result = app(WhatsAppClient::class)->sendTextMessage('+201000000003', 'Task closed message');

        $this->assertFalse($result->sent);
        $this->assertSame('failed', $result->status);
        $this->assertSame(400, $result->httpStatusCode);

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $result->messageRecord->id,
            'direction' => WhatsappMessageDirection::OUTBOUND->value,
            'to_phone' => '+201000000003',
            'status' => 'failed',
            'sent_at' => null,
        ]);
    }

    public function test_send_text_message_uses_database_api_settings_over_config_values(): void
    {
        config([
            'whatsapp.outbound_enabled' => false,
            'whatsapp.access_token' => 'config-access-token',
            'whatsapp.phone_number_id' => 'config-phone-id',
            'whatsapp.api_base_url' => 'https://graph.facebook.com',
            'whatsapp.graph_version' => 'v23.0',
        ]);

        ApiSetting::query()->create([
            'provider' => 'meta',
            'outbound_enabled' => true,
            'access_token' => 'db-access-token',
            'phone_number_id' => 'db-phone-id',
            'api_base_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
        ]);

        Http::fake([
            'https://graph.facebook.com/v23.0/db-phone-id/messages' => Http::response([
                'messages' => [['id' => 'wamid.DB.001']],
            ], 200),
        ]);

        $result = app(WhatsAppClient::class)->sendTextMessage('+201000000004', 'DB override message');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v23.0/db-phone-id/messages'
                && $request->hasHeader('Authorization', 'Bearer db-access-token');
        });

        $this->assertTrue($result->sent);
        $this->assertSame('sent', $result->status);
        $this->assertSame('db-phone-id', $result->messageRecord->business_phone_number_id);
    }
}
