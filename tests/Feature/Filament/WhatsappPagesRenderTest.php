<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\WhatsAppSession;
use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Services\Authorization\RbacInitializationService;
use App\Services\WhatsApp\BridgeApiClient;
use App\Services\WhatsApp\WhatsappBridgeProcessService;
use App\Services\WhatsApp\WhatsappBridgeStatusService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_render_whatsapp_inbox_and_contacts_pages(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $contact = WhatsappContact::factory()->create([
            'name' => 'فرع القاهرة',
            'phone' => '201234567890',
        ]);

        $this->fakeWhatsappUiDependencies();

        $this->actingAs($dispatcher)
            ->get(WhatsappMessageResource::getUrl('index'))
            ->assertOk()
            ->assertSee('محادثات واتساب')
            ->assertSee('يحتاج QR');

        $this->actingAs($dispatcher)
            ->get(WhatsappContactResource::getUrl('index'))
            ->assertOk()
            ->assertSee('جهات اتصال واتساب');

        $this->actingAs($dispatcher)
            ->get(WhatsappContactResource::getUrl('create'))
            ->assertOk()
            ->assertSee('إضافة جهة اتصال واتساب');

        $this->actingAs($dispatcher)
            ->get(WhatsappContactResource::getUrl('view', ['record' => $contact]))
            ->assertOk()
            ->assertSee('عرض جهة اتصال واتساب')
            ->assertSee('فتح المحادثة');

        $this->actingAs($dispatcher)
            ->get(WhatsappContactResource::getUrl('edit', ['record' => $contact]))
            ->assertOk()
            ->assertSee('تعديل جهة اتصال واتساب');
    }

    public function test_admin_can_render_whatsapp_session_page(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $this->fakeWhatsappUiDependencies();

        $this->actingAs($admin)
            ->get(WhatsAppSession::getUrl())
            ->assertOk()
            ->assertSee('جلسة واتساب')
            ->assertSee('يحتاج QR');
    }

    private function fakeWhatsappUiDependencies(): void
    {
        app()->instance(WhatsappBridgeStatusService::class, new class
        {
            /**
             * @return array<string, mixed>
             */
            public function current(): array
            {
                return [
                    'state' => 'qr_required',
                    'label' => 'يحتاج QR',
                    'badge' => 'warning',
                    'hex' => '#f59e0b',
                    'status_hint' => 'يجب مسح QR قبل الإرسال.',
                    'supports_bridge' => true,
                    'outbound_enabled' => true,
                    'can_send' => false,
                    'can_queue' => true,
                    'account_id' => null,
                    'account_name' => null,
                    'group_name' => null,
                    'groups_count' => 0,
                    'qr_available' => false,
                    'last_heartbeat_at' => null,
                    'last_message_at' => null,
                    'pm2_status' => 'online',
                    'pm2_found' => true,
                    'auth_exists' => true,
                    'restart_requested' => false,
                ];
            }
        });

        app()->instance(BridgeApiClient::class, new class
        {
            /**
             * @return array<string, mixed>
             */
            public function getQr(): array
            {
                return ['qr' => null];
            }
        });

        app()->instance(WhatsappBridgeProcessService::class, new class
        {
            /**
             * @return array<string, mixed>
             */
            public function getDiagnostics(): array
            {
                return [
                    'pm2_found' => true,
                    'pm2_bin' => '/usr/bin/pm2',
                    'node_version' => 'v20.0.0',
                    'bridge_status' => 'online',
                    'restart_count' => 0,
                    'uptime' => '5m 0s',
                    'memory' => '55 MB',
                    'auth_exists' => true,
                    'pid' => 1234,
                ];
            }

            /**
             * @return array{output:string,error:string}
             */
            public function getLogs(int $lines = 0): array
            {
                return [
                    'output' => 'bridge started',
                    'error' => '',
                ];
            }
        });
    }
}
