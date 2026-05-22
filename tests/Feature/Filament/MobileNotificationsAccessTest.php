<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Models\MobileNotification;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MobileNotificationsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_mobile_notifications_resource(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->actingAs($dispatcher);

        $this->assertTrue(Gate::forUser($dispatcher)->allows('viewAny', MobileNotification::class));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('create', MobileNotification::class));
    }

    public function test_dispatcher_can_open_mobile_notifications_pages(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->actingAs($dispatcher);

        $this->get(MobileNotificationResource::getUrl())->assertOk();
        $this->get(MobileNotificationResource::getUrl('create'))->assertOk();
    }

    public function test_employee_cannot_access_mobile_notifications_resource(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $this->actingAs($employee);

        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', MobileNotification::class));
        $this->assertFalse(Gate::forUser($employee)->allows('create', MobileNotification::class));
    }
}
