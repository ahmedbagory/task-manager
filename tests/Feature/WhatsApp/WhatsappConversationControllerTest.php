<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsappMessageDirection;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_send_bridge_message_and_store_outgoing_record(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        config([
            'whatsapp.provider' => 'whatsapp_web_bridge',
            'whatsapp.outbound_enabled' => true,
            'whatsapp.bridge_api_port' => 3001,
        ]);

        Http::fake([
            'http://127.0.0.1:3001/send-message' => Http::response([
                'success' => true,
                'status' => 'sent',
                'provider_message_id' => 'wamid.bridge.out.001',
                'sent_to' => '966500000000@s.whatsapp.net',
            ], 200),
        ]);

        $response = $this->actingAs($dispatcher)
            ->from('/admin/whatsapp-messages?contact=1')
            ->post(route('whatsapp.messages.send'), [
                'phone' => '966500000000',
                'body' => 'Outbound bridge test',
            ]);

        $response->assertRedirect('/admin/whatsapp-messages?contact=1');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:3001/send-message'
            && $request['to'] === '966500000000'
            && $request['message'] === 'Outbound bridge test');

        $this->assertDatabaseHas('whatsapp_messages', [
            'direction' => WhatsappMessageDirection::OUTBOUND->value,
            'to_phone' => '966500000000',
            'body' => 'Outbound bridge test',
            'status' => 'sent',
            'external_message_id' => 'wamid.bridge.out.001',
            'sent_by_user_id' => $dispatcher->id,
        ]);
    }

    public function test_dispatcher_can_send_attachment_via_bridge(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        Storage::fake('public');

        config([
            'whatsapp.provider' => 'whatsapp_web_bridge',
            'whatsapp.outbound_enabled' => true,
            'whatsapp.bridge_api_port' => 3001,
        ]);

        Http::fake([
            'http://127.0.0.1:3001/send-message' => Http::response([
                'success' => true,
                'status' => 'sent',
                'provider_message_id' => 'wamid.bridge.out.attachment.001',
                'sent_to' => '966500000010@s.whatsapp.net',
            ], 200),
        ]);

        $response = $this->actingAs($dispatcher)
            ->from('/admin/whatsapp-messages?contact=1')
            ->post(route('whatsapp.messages.send'), [
                'phone' => '966500000010',
                'body' => 'Please check the file.',
                'attachment' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ]);

        $response->assertRedirect('/admin/whatsapp-messages?contact=1');

        $message = WhatsappMessage::query()->latest('id')->first();

        $this->assertNotNull($message);
        $this->assertSame('document', $message->media_type);
        $this->assertSame('application/pdf', $message->media_mime);
        $this->assertNotNull($message->media_path);
        $this->assertNotNull($message->media_url);
        $this->assertSame('report.pdf', $message->media_name);
        $this->assertSame('sent', $message->status);

        Storage::disk('public')->assertExists($message->media_path);
    }

    public function test_failed_message_can_be_retried(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        config([
            'whatsapp.provider' => 'whatsapp_web_bridge',
            'whatsapp.outbound_enabled' => true,
            'whatsapp.bridge_api_port' => 3001,
        ]);

        Http::fake([
            'http://127.0.0.1:3001/send-message' => Http::response([
                'success' => true,
                'status' => 'sent',
                'provider_message_id' => 'wamid.bridge.retry.001',
                'sent_to' => '966500000020@s.whatsapp.net',
            ], 200),
        ]);

        $message = WhatsappMessage::factory()->create([
            'direction' => WhatsappMessageDirection::OUTBOUND->value,
            'to_phone' => '966500000020',
            'body' => 'Retry me',
            'status' => 'failed',
            'failed_reason' => 'Bridge offline',
        ]);

        $response = $this->actingAs($dispatcher)
            ->from('/admin/whatsapp-messages?contact=1')
            ->post(route('whatsapp.messages.retry', $message));

        $response->assertRedirect('/admin/whatsapp-messages?contact=1');

        $this->assertDatabaseHas('whatsapp_messages', [
            'id' => $message->id,
            'status' => 'sent',
            'external_message_id' => 'wamid.bridge.retry.001',
            'failed_reason' => null,
        ]);
    }
}
