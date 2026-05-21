<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Models\WhatsappContact;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class WhatsappContactsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_whatsapp_contacts_management(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $this->assertTrue($dispatcher->can('whatsapp_contacts.view'));
        $this->assertTrue($dispatcher->can('whatsapp_contacts.manage'));
        $this->assertTrue(Gate::forUser($dispatcher)->allows('viewAny', WhatsappContact::class));
    }

    public function test_employee_cannot_access_whatsapp_contacts_management(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $this->assertFalse($employee->can('whatsapp_contacts.view'));
        $this->assertFalse($employee->can('whatsapp_contacts.manage'));
        $this->assertFalse(Gate::forUser($employee)->allows('viewAny', WhatsappContact::class));
    }
}
