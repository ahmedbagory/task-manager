<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\MobileNotifications;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNotificationsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_mobile_notifications_page(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->actingAs($dispatcher);

        $this->assertTrue(MobileNotifications::canAccess());
    }

    public function test_dispatcher_can_open_mobile_notifications_page(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->actingAs($dispatcher);

        $this->get(MobileNotifications::getUrl())
            ->assertOk();
    }

    public function test_employee_cannot_access_mobile_notifications_page(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $this->actingAs($employee);

        $this->assertFalse(MobileNotifications::canAccess());
    }
}
