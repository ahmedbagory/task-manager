<?php

namespace Tests\Feature\Jobs;

use App\Enums\MobileNotificationRecipientStatus;
use App\Enums\MobileNotificationStatus;
use App\Jobs\SendMobileNotificationJob;
use App\Models\Department;
use App\Models\MobileNotification;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Notifications\MobileNotificationAudienceResolver;
use App\Services\Notifications\MobileNotificationService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SendMobileNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_records_recipients_and_marks_notification_as_sent(): void
    {
        app(RbacInitializationService::class)->seed();

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

        $employeeWithDevice = User::factory()->create(['department_id' => $unit->id]);
        $employeeWithDevice->assignRole(Rbac::EMPLOYEE);

        $employeeWithoutDevice = User::factory()->create(['department_id' => $unit->id]);
        $employeeWithoutDevice->assignRole(Rbac::EMPLOYEE);

        $mobileNotification = MobileNotification::query()->create([
            'title' => 'Maintenance Update',
            'body' => 'Please check the app for today updates.',
            'status' => MobileNotificationStatus::QUEUED,
            'created_by' => $dispatcher->id,
            'queued_at' => now(),
        ]);

        $mobileNotification->targets()->create([
            'target_type' => 'department',
            'target_id' => $mainDepartment->id,
        ]);

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employeeWithDevice, $employeeWithoutDevice, $mobileNotification): void {
            $mock->shouldReceive('countTokensForUser')
                ->twice()
                ->withArgs(function (User $recipient): bool {
                    return true;
                })
                ->andReturnUsing(function (User $recipient) use ($employeeWithDevice, $employeeWithoutDevice): int {
                    return match ($recipient->id) {
                        $employeeWithDevice->id => 1,
                        $employeeWithoutDevice->id => 0,
                        default => 0,
                    };
                });

            $mock->shouldReceive('sendNotificationToUser')
                ->once()
                ->withArgs(function (User $recipient, string $title, string $body, array $data) use ($employeeWithDevice, $mobileNotification): bool {
                    return $recipient->is($employeeWithDevice)
                        && $title === 'Maintenance Update'
                        && $body === 'Please check the app for today updates.'
                        && $data['type'] === 'manual_broadcast'
                        && $data['mobile_notification_id'] === (string) $mobileNotification->id;
                })
                ->andReturn(1);
        });

        $job = new SendMobileNotificationJob($mobileNotification->id);
        $job->handle(
            app(MobileNotificationAudienceResolver::class),
            app(MobileNotificationService::class),
            app(FcmNotificationService::class),
        );

        $mobileNotification->refresh();

        $this->assertSame(MobileNotificationStatus::SENT, $mobileNotification->status);
        $this->assertSame(2, $mobileNotification->targeted_users_count);
        $this->assertSame(1, $mobileNotification->targeted_users_with_devices_count);
        $this->assertSame(1, $mobileNotification->targeted_devices_count);

        $this->assertDatabaseHas('mobile_notification_recipients', [
            'mobile_notification_id' => $mobileNotification->id,
            'user_id' => $employeeWithDevice->id,
            'status' => MobileNotificationRecipientStatus::SENT->value,
            'device_count' => 1,
            'delivered_devices_count' => 1,
        ]);
        $this->assertDatabaseHas('mobile_notification_recipients', [
            'mobile_notification_id' => $mobileNotification->id,
            'user_id' => $employeeWithoutDevice->id,
            'status' => MobileNotificationRecipientStatus::SKIPPED_NO_DEVICE->value,
            'device_count' => 0,
            'delivered_devices_count' => 0,
        ]);
    }
}
