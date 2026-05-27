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

        $response = $this->actingAs($admin)
            ->get(WhatsAppSession::getUrl());

        $response
            ->assertOk()
            ->assertSee('جلسة واتساب')
            ->assertSee('يحتاج QR')
            ->assertSee('مراقبة حالة الربط والبريدج')
            ->assertSee('إعادة تشغيل البريدج')
            ->assertSee('إعادة الربط / Reconnect')
            ->assertSee('فصل الجلسة')
            ->assertSee('الجلسة تحتاج QR لكن الرمز غير متاح بعد');

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'data-whatsapp-session-status-badge'));
        $this->assertSame(1, substr_count($content, 'data-whatsapp-session-primary-actions'));
        $this->assertSame(1, preg_match_all('/>\s*يحتاج QR\s*</u', $content));
    }

    public function test_admin_connected_session_shows_single_status_badge_and_single_action_bar(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $this->fakeWhatsappUiDependencies([
            'status' => [
                'state' => 'ready',
                'label' => 'متصل',
                'badge' => 'success',
                'status_hint' => 'الجلسة جاهزة لإرسال الرسائل واستقبالها.',
                'can_send' => true,
                'can_queue' => false,
                'account_id' => '201234567890',
                'account_name' => 'Main Session',
                'qr_available' => true,
            ],
            'qr' => [
                'qr' => 'data:image/png;base64,abc123',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->get(WhatsAppSession::getUrl());

        $response
            ->assertOk()
            ->assertSee('متصل')
            ->assertSee('إعادة تشغيل البريدج')
            ->assertSee('إعادة الربط / Reconnect')
            ->assertSee('فصل الجلسة')
            ->assertSee('لا يوجد QR مطلوب حاليًا')
            ->assertSee('السجلات');

        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, 'data-whatsapp-session-status-badge'));
        $this->assertSame(1, substr_count($content, 'data-whatsapp-session-primary-actions'));
        $this->assertSame(1, preg_match_all('/>\s*متصل\s*</u', $content));
        $this->assertStringNotContainsString('عرض QR', $content);
    }

    public function test_whatsapp_inbox_layout_follows_the_active_locale_direction(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->fakeWhatsappUiDependencies();

        app()->setLocale('en');

        $this->actingAs($dispatcher)
            ->get(WhatsappMessageResource::getUrl('index'))
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('xl:flex-row', false)
            ->assertDontSee('xl:flex-row-reverse', false);
    }

    /**
     * @param  array{
     *     status?: array<string, mixed>,
     *     qr?: array<string, mixed>,
     *     diagnostics?: array<string, mixed>,
     *     logs?: array{output:string,error:string}
     * }  $overrides
     */
    private function fakeWhatsappUiDependencies(array $overrides = []): void
    {
        $status = array_replace([
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
        ], $overrides['status'] ?? []);

        $qr = array_replace([
            'qr' => null,
        ], $overrides['qr'] ?? []);

        $diagnostics = array_replace([
            'pm2_found' => true,
            'pm2_bin' => '/usr/bin/pm2',
            'node_version' => 'v20.0.0',
            'bridge_status' => 'online',
            'restart_count' => 0,
            'uptime' => '5m 0s',
            'memory' => '55 MB',
            'auth_exists' => true,
            'pid' => 1234,
        ], $overrides['diagnostics'] ?? []);

        $logs = array_replace([
            'output' => 'bridge started',
            'error' => '',
        ], $overrides['logs'] ?? []);

        app()->instance(WhatsappBridgeStatusService::class, new class
            ($status)
        {
            /**
             * @param  array<string, mixed>  $status
             */
            public function __construct(
                private readonly array $status,
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function current(): array
            {
                return $this->status;
            }
        });

        app()->instance(BridgeApiClient::class, new class
            ($qr)
        {
            /**
             * @param  array<string, mixed>  $qr
             */
            public function __construct(
                private readonly array $qr,
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getQr(): array
            {
                return $this->qr;
            }
        });

        app()->instance(WhatsappBridgeProcessService::class, new class
            ($diagnostics, $logs)
        {
            /**
             * @param  array<string, mixed>  $diagnostics
             * @param  array{output:string,error:string}  $logs
             */
            public function __construct(
                private readonly array $diagnostics,
                private readonly array $logs,
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getDiagnostics(): array
            {
                return $this->diagnostics;
            }

            /**
             * @return array{output:string,error:string}
             */
            public function getLogs(int $lines = 0): array
            {
                return $this->logs;
            }
        });
    }
}
