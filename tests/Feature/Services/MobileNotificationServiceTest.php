<?php

namespace Tests\Feature\Services;

use App\Jobs\SendMobileNotificationJob;
use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\MobileNotificationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MobileNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_queued_notification_persists_targets_and_dispatches_job(): void
    {
        app(RbacInitializationService::class)->seed();
        Queue::fake();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $mainDepartment = Department::factory()->create([
            'is_active' => true,
            'parent_id' => null,
        ]);
        $unit = Department::factory()->create([
            'is_active' => true,
            'parent_id' => $mainDepartment->id,
        ]);

        $employee = User::factory()->create(['department_id' => $unit->id]);
        $employee->assignRole(Rbac::EMPLOYEE);

        DeviceToken::query()->create([
            'user_id' => $employee->id,
            'device_id' => 'device-employee-1',
            'fcm_token' => 'token-employee-1',
            'device_type' => 'android',
        ]);

        $mobileNotification = app(MobileNotificationService::class)->createQueuedNotification([
            'title' => 'Maintenance Update',
            'body' => 'Please check the app for today updates.',
            'target_department_ids' => [$mainDepartment->id],
            'target_user_ids' => [$employee->id],
        ], $dispatcher);

        $this->assertDatabaseHas('mobile_notifications', [
            'id' => $mobileNotification->id,
            'title' => 'Maintenance Update',
            'created_by' => $dispatcher->id,
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('mobile_notification_targets', [
            'mobile_notification_id' => $mobileNotification->id,
            'target_type' => 'department',
            'target_id' => $mainDepartment->id,
        ]);
        $this->assertDatabaseHas('mobile_notification_targets', [
            'mobile_notification_id' => $mobileNotification->id,
            'target_type' => 'user',
            'target_id' => $employee->id,
        ]);

        Queue::assertPushed(SendMobileNotificationJob::class, function (SendMobileNotificationJob $job) use ($mobileNotification): bool {
            return $job->mobileNotificationId === $mobileNotification->id;
        });
    }
}
