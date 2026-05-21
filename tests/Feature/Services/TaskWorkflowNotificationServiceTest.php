<?php

namespace Tests\Feature\Services;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Authorization\RbacInitializationService;
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

        $this->assertNotificationWithTitleContains($admin, 'New WhatsApp message received');
        $this->assertNotificationWithTitleContains($dispatcher, 'New WhatsApp message received');
        $this->assertNoNotificationWithTitleContains($employee, 'New WhatsApp message received');
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

        $this->assertNotificationWithTitleContains($admin, "converted to task {$task->task_number}");
        $this->assertNotificationWithTitleContains($dispatcher, "converted to task {$task->task_number}");
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

        $this->assertNotificationWithTitleContains($employee, "Task {$task->task_number} assigned to you");
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

        $this->assertNotificationWithTitleContains($admin, "Task {$task->task_number} was rejected");
        $this->assertNotificationWithTitleContains($dispatcher, "Task {$task->task_number} was rejected");
    }

    public function test_task_completion_notifies_dispatchers_and_admins(): void
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

        app(TaskAssignmentService::class)->completeAssignedTask($assignment, $employee);

        $this->assertNotificationWithTitleContains($admin, "Task {$task->task_number} completed");
        $this->assertNotificationWithTitleContains($dispatcher, "Task {$task->task_number} completed");
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

        $this->assertTrue(
            $titles->contains(fn (string $title): bool => str_contains($title, $text)),
            "Expected notification containing [{$text}] was not found for user {$user->id}."
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
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', (string) $user->id)
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
