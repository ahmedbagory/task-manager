<?php

namespace Tests\Feature\Services;

use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\MobileNotificationAudienceResolver;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNotificationAudienceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_deduplicates_resolved_users_and_counts_devices(): void
    {
        app(RbacInitializationService::class)->seed();

        $mainDepartment = Department::factory()->create([
            'name' => 'Operations',
            'is_active' => true,
            'parent_id' => null,
        ]);
        $unit = Department::factory()->create([
            'name' => 'Field Team',
            'is_active' => true,
            'parent_id' => $mainDepartment->id,
        ]);

        $employee = User::factory()->create(['department_id' => $unit->id]);
        $employee->assignRole(Rbac::EMPLOYEE);

        $supervisor = User::factory()->create(['department_id' => $unit->id]);
        $supervisor->assignRole(Rbac::SUPERVISOR);

        $admin = User::factory()->create(['department_id' => $unit->id]);
        $admin->assignRole(Rbac::ADMIN);

        DeviceToken::query()->create([
            'user_id' => $employee->id,
            'device_id' => 'device-employee-1',
            'fcm_token' => 'token-employee-1',
            'device_type' => 'android',
        ]);
        DeviceToken::query()->create([
            'user_id' => $supervisor->id,
            'device_id' => 'device-supervisor-1',
            'fcm_token' => 'token-supervisor-1',
            'device_type' => 'android',
        ]);
        DeviceToken::query()->create([
            'user_id' => $supervisor->id,
            'device_id' => 'device-supervisor-2',
            'fcm_token' => 'token-supervisor-2',
            'device_type' => 'android',
        ]);

        $preview = app(MobileNotificationAudienceResolver::class)->preview([
            'target_department_ids' => [$mainDepartment->id],
            'target_user_ids' => [$employee->id, $admin->id],
        ]);

        $this->assertSame(
            [$employee->id, $supervisor->id],
            $preview['users']->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame(2, $preview['user_count']);
        $this->assertSame(2, $preview['users_with_devices_count']);
        $this->assertSame(3, $preview['device_count']);
    }
}
