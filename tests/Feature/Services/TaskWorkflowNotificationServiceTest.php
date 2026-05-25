<?php

namespace Tests\Feature\Services;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\TaskWorkflowNotificationService;
use App\Services\Tasks\TaskAssignmentService;
use App\Services\WhatsApp\WhatsAppInboxService;
use App\Services\WhatsApp\WhatsAppWebhookService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskWorkflowNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_writes_database_notifications_directly(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        app(TaskWorkflowNotificationService::class)->notifyReporterConfirmationRequested($task, $user);

        $notification = DB::table('notifications')->first();

        $this->assertNotNull($notification);
        $this->assertSame($user->getMorphClass(), $notification->notifiable_type);

        $data = json_decode((string) $notification->data, true);

        $this->assertSame('بانتظار تأكيد حل المشكلة', $data['title'] ?? null);
    }

    public function test_new_whatsapp_message_notifies_dispatchers_and_admins(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        app(WhatsAppWebhookService::class)->handleInbound($this->sampleInboundPayload());

        $this->assertNotificationWithTitleContains($admin, 'رسالة واتساب جديدة');
        $this->assertNotificationWithTitleContains($dispatcher, 'رسالة واتساب جديدة');
        $this->assertNoNotificationWithTitleContains($employee, 'رسالة واتساب جديدة');
    }

    public function test_message_conversion_to_task_notifies_dispatchers_and_admins(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000200',
            'body' => 'Water leakage in warehouse',
        ]);

        $task = app(WhatsAppInboxService::class)->convertMessageToTask(
            message: $message,
            data: [
                'title' => 'Warehouse water leakage',
                'priority' => 'high',
            ],
            actor: $dispatcher,
        );

        $this->assertNotificationWithTitleContains($admin, "تم تحويل رسالة واتساب إلى مهمة {$task->task_number}");
        $this->assertNotificationWithTitleContains($dispatcher, "تم تحويل رسالة واتساب إلى مهمة {$task->task_number}");
    }

    public function test_task_assignment_notifies_assigned_employee(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'source' => TaskSource::MANUAL->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'assigned_to_user_id' => null,
        ]);

        app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Please handle quickly');

        $this->assertNotificationWithTitleContains($employee, 'تم إسناد مهمة جديدة إليك');
    }

    public function test_task_rejection_notifies_dispatchers_and_admins(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'source' => TaskSource::MANUAL->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'assigned_to_user_id' => null,
        ]);

        $assignment = app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Handle issue');

        app(TaskAssignmentService::class)->rejectAssignment($assignment, $employee, 'Need electrical team');

        $this->assertNotificationWithTitleContains($admin, 'تم رفض المهمة');
        $this->assertNotificationWithTitleContains($dispatcher, 'تم رفض المهمة');
    }

    public function test_reporter_confirmation_request_and_final_closure_notifications_are_sent(): void
    {
        app(RbacInitializationService::class)->seed();

        $admin = User::factory()->create();
        $admin->assignRole(Rbac::ADMIN);

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $reporter = User::factory()->create();
        $reporter->assignRole(Rbac::EMPLOYEE);

        $task = Task::factory()->create([
            'source' => TaskSource::MANUAL->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'assigned_to_user_id' => null,
            'reported_by_user_id' => $reporter->id,
        ]);

        $assignment = app(TaskAssignmentService::class)->assignTask($task, $employee->id, $dispatcher, 'Handle issue');
        app(TaskAssignmentService::class)->acceptAssignment($assignment, $employee);
        app(TaskAssignmentService::class)->startTask($assignment, $employee);

        app(TaskAssignmentService::class)->completeAssignedTask($assignment, $employee);

        $this->assertNotificationWithTitleContains($reporter, 'بانتظار تأكيد حل المشكلة');

        app(TaskAssignmentService::class)->confirmResolution($task, $reporter);

        $this->assertNotificationWithTitleContains($admin, "تم إكمال المهمة {$task->task_number}");
        $this->assertNotificationWithTitleContains($dispatcher, "تم إكمال المهمة {$task->task_number}");
        $this->assertNotificationWithTitleContains($employee, 'تم تأكيد حل المشكلة');
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleInboundPayload(): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => [
                            'display_phone_number' => '+201000000099',
                            'phone_number_id' => '1234567890',
                        ],
                        'contacts' => [[
                            'wa_id' => '201000000099',
                            'profile' => [
                                'name' => 'Facility Reporter',
                            ],
                        ]],
                        'messages' => [[
                            'id' => 'wamid.HBgMTESTNOTIFY001',
                            'from' => '201000000099',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => [
                                'body' => 'AC is not working on floor 2',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function assertNotificationWithTitleContains(User $user, string $text): void
    {
        $titles = $this->notificationTitlesForUser($user);
        $allNotifications = DB::table('notifications')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (object $row): array {
                $data = json_decode((string) $row->data, true);

                return [
                    'notifiable_type' => $row->notifiable_type,
                    'notifiable_id' => $row->notifiable_id,
                    'title' => is_array($data) ? ($data['title'] ?? null) : null,
                ];
            })
            ->all();

        $this->assertTrue(
            $titles->contains(fn (string $title): bool => str_contains($title, $text)),
            "Expected notification containing [{$text}] was not found for user {$user->id}. Actual titles: "
            .json_encode($titles->all(), JSON_UNESCAPED_UNICODE)
            .' All notifications: '
            .json_encode($allNotifications, JSON_UNESCAPED_UNICODE)
        );
    }

    private function assertNoNotificationWithTitleContains(User $user, string $text): void
    {
        $titles = $this->notificationTitlesForUser($user);

        $this->assertFalse(
            $titles->contains(fn (string $title): bool => str_contains($title, $text)),
            "Unexpected notification containing [{$text}] found for user {$user->id}."
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function notificationTitlesForUser(User $user): Collection
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->map(function (object $row): ?string {
                $data = json_decode((string) $row->data, true);

                if (! is_array($data)) {
                    return null;
                }

                $title = $data['title'] ?? null;

                return is_string($title) ? $title : null;
            })
            ->filter()
            ->values();
    }
}
