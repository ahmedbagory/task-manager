<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\WhatsApp\WhatsappBridgeStatusService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappBridgeStatusRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_receives_live_bridge_status_payload(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

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
                    'groups_count' => 0,
                ];
            }
        });

        $this->actingAs($admin)
            ->getJson(route('whatsapp.bridge-status'))
            ->assertOk()
            ->assertJson([
                'state' => 'qr_required',
                'label' => 'يحتاج QR',
                'badge' => 'warning',
                'hex' => '#f59e0b',
                'can_send' => false,
                'can_queue' => true,
            ]);
    }
}
