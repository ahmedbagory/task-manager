<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ApiSettings;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_api_settings_feature(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $this->actingAs($admin);

        $this->assertTrue(ApiSettings::canAccess());
    }

    public function test_employee_cannot_access_api_settings_feature(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $this->actingAs($employee);

        $this->assertFalse(ApiSettings::canAccess());
    }
}
