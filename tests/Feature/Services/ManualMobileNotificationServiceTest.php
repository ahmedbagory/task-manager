<?php

namespace Tests\Feature\Services;

use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Notifications\ManualMobileNotificationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ManualMobileNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_audience_deduplicates_targets_and_ignores_non_employee_roles(): void
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

        $preview = app(ManualMobileNotificationService::class)->previewAudience([
            'departments' => [$mainDepartment->id],
            'users' => [$employee->id, $admin->id],
        ]);

        $this->assertSame(
            [$employee->id, $supervisor->id],
            $preview['users']->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame(2, $preview['user_count']);
        $this->assertSame(2, $preview['users_with_devices_count']);
        $this->assertSame(3, $preview['device_count']);
    }

    public function test_send_dispatches_manual_notification_to_resolved_audience(): void
    {
        app(RbacInitializationService::class)->seed();

        $mainDepartment = Department::factory()->create([
            'is_active' => true,
            'parent_id' => null,
        ]);
        $unit = Department::factory()->create([
            'is_active' => true,
            'parent_id' => $mainDepartment->id,
        ]);

        $sender = User::factory()->create();
        $sender->assignRole(Rbac::ADMIN);

        $employee = User::factory()->create(['department_id' => $unit->id]);
        $employee->assignRole(Rbac::EMPLOYEE);

        DeviceToken::query()->create([
            'user_id' => $employee->id,
            'device_id' => 'device-employee-1',
            'fcm_token' => 'token-employee-1',
            'device_type' => 'android',
        ]);

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employee, $sender): void {
            $mock->shouldReceive('sendManualNotification')
                ->once()
                ->withArgs(function (iterable $recipients, string $title, string $body, array $data) use ($employee, $sender): bool {
                    return collect($recipients)->pluck('id')->values()->all() === [$employee->id]
                        && $title === 'Maintenance Update'
                        && $body === 'Please check the app for today updates.'
                        && $data['type'] === 'manual_broadcast'
                        && $data['sender_id'] === (string) $sender->id;
                })
                ->andReturn([
                    'targeted_users' => 1,
                    'targeted_users_with_devices' => 1,
                    'targeted_devices' => 1,
                ]);
        });

        $result = app(ManualMobileNotificationService::class)->send(
            title: 'Maintenance Update',
            body: 'Please check the app for today updates.',
            targets: [
                'departments' => [$mainDepartment->id],
                'units' => [],
                'users' => [],
            ],
            sender: $sender,
        );

        $this->assertSame(1, $result['user_count']);
        $this->assertSame(1, $result['users_with_devices_count']);
        $this->assertSame(1, $result['device_count']);
        $this->assertSame(1, $result['targeted_users']);
        $this->assertSame(1, $result['targeted_users_with_devices']);
        $this->assertSame(1, $result['targeted_devices']);
    }
}
