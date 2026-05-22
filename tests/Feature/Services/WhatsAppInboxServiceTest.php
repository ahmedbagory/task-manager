<?php

namespace Tests\Feature\Services;

use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Exceptions\WhatsAppMessageAlreadyConvertedException;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Authorization\RbacInitializationService;
use App\Services\WhatsApp\WhatsAppInboxService;
use App\Support\Rbac;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppInboxServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_message_to_task_creates_whatsapp_task_and_links_message(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $department = Department::factory()->create(['is_active' => true]);
        $category = TaskCategory::factory()->create([
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000001',
            'body' => 'Water leakage in storage room.',
        ]);

        $task = app(WhatsAppInboxService::class)->convertMessageToTask($message, [
            'title' => 'Storage room leakage',
            'description' => 'Reporter says leakage is increasing.',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'location' => 'Building B - Storage',
            'due_at' => now()->addHours(12),
            'assigned_to_user_id' => null,
        ], $dispatcher);

        $this->assertSame(TaskSource::WHATSAPP, $task->source);
        $this->assertSame(TaskStatus::PENDING_ASSIGNMENT, $task->status);
        $this->assertSame('+201000000001', $task->reported_by_phone);
        $this->assertSame($task->id, $message->fresh()->task_id);
        $this->assertStringContainsString('Water leakage in storage room.', (string) $task->description);
        $this->assertStringContainsString('Reporter says leakage is increasing.', (string) $task->description);
    }

    public function test_convert_message_to_task_assigns_employee_when_selected(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000002',
            'body' => 'Electrical short circuit in office 4.',
        ]);

        $task = app(WhatsAppInboxService::class)->convertMessageToTask($message, [
            'title' => 'Office 4 short circuit',
            'priority' => 'urgent',
            'assigned_to_user_id' => $employee->id,
        ], $dispatcher);

        $assignment = TaskAssignment::query()->where('task_id', $task->id)->first();

        $this->assertNotNull($assignment);
        $this->assertSame(TaskAssignmentStatus::ASSIGNED, $assignment->status);
        $this->assertSame($employee->id, $assignment->assigned_to_user_id);
        $this->assertSame(TaskStatus::ASSIGNED, $task->fresh()->status);
        $this->assertSame($employee->id, $task->fresh()->assigned_to_user_id);
    }

    public function test_convert_message_to_task_prevents_duplicate_conversion(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000003',
            'body' => 'Broken window in hallway.',
        ]);

        $task = app(WhatsAppInboxService::class)->convertMessageToTask($message, [
            'title' => 'Broken hallway window',
            'priority' => 'medium',
        ], $dispatcher);

        $this->expectException(WhatsAppMessageAlreadyConvertedException::class);

        try {
            app(WhatsAppInboxService::class)->convertMessageToTask($message->fresh(), [
                'title' => 'Second conversion should fail',
                'priority' => 'low',
            ], $dispatcher);
        } finally {
            $this->assertSame(1, Task::query()->count());
            $this->assertSame($task->id, $message->fresh()->task_id);
        }
    }

    public function test_convert_message_to_task_requires_dispatcher_or_admin(): void
    {
        app(RbacInitializationService::class)->seed();

        $employee = User::factory()->create();
        $employee->assignRole(Rbac::EMPLOYEE);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'direction' => 'inbound',
            'from_phone' => '+201000000004',
            'body' => 'Lighting issue in meeting room.',
        ]);

        $this->expectException(AuthorizationException::class);

        app(WhatsAppInboxService::class)->convertMessageToTask($message, [
            'title' => 'Meeting room lighting issue',
            'priority' => 'medium',
        ], $employee);
    }

    public function test_convert_message_to_task_uses_contact_department_mapping_as_defaults(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $department = Department::factory()->create(['is_active' => true, 'name' => 'Mega 6']);
        $contact = WhatsappContact::factory()->create([
            'phone' => '201555960069',
            'department_id' => $department->id,
            'default_location' => 'ميجا 6',
        ]);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'from_phone' => '201555960069',
            'body' => 'Air conditioner issue in location.',
        ]);

        $task = app(WhatsAppInboxService::class)->convertMessageToTask($message, [
            'title' => 'Location AC issue',
            'priority' => 'medium',
        ], $dispatcher);

        $this->assertSame($department->id, $task->department_id);
        $this->assertSame('ميجا 6', $task->location);
    }

    public function test_convert_message_to_task_uses_participant_phone_mapping_when_message_phone_is_lid(): void
    {
        app(RbacInitializationService::class)->seed();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $mappedContact = WhatsappContact::factory()->create([
            'phone' => '201555960069',
            'default_location' => 'ميجا 6',
        ]);

        $lidContact = WhatsappContact::factory()->create([
            'phone' => '167366503714914',
            'default_location' => null,
            'department_id' => null,
        ]);

        $message = WhatsappMessage::factory()->create([
            'task_id' => null,
            'contact_id' => $lidContact->id,
            'direction' => 'inbound',
            'from_phone' => '167366503714914',
            'body' => 'Issue from department group',
            'raw_payload' => [
                'payload' => [
                    'raw_payload' => [
                        'key' => [
                            'participantPn' => '201555960069@s.whatsapp.net',
                        ],
                    ],
                ],
            ],
        ]);

        $task = app(WhatsAppInboxService::class)->convertMessageToTask($message, [
            'title' => 'Department issue',
            'priority' => 'medium',
        ], $dispatcher);

        $this->assertSame('ميجا 6', $task->location);
        $this->assertSame('201555960069', $task->reported_by_phone);
        $this->assertNotNull($mappedContact);
    }
}
