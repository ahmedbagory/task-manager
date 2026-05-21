<?php

namespace Tests\Feature\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Reports\TaskReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_overview_metrics_for_filtered_tasks(): void
    {
        $department = Department::factory()->create();
        $category = TaskCategory::factory()->create(['department_id' => $department->id]);
        $employee = User::factory()->create();

        $completedTask = Task::factory()->create([
            'department_id' => $department->id,
            'category_id' => $category->id,
            'assigned_to_user_id' => $employee->id,
            'source' => TaskSource::WHATSAPP->value,
            'status' => TaskStatus::COMPLETED->value,
            'priority' => TaskPriority::HIGH->value,
            'created_at' => now()->subHours(5),
            'completed_at' => now()->subHours(1),
            'due_at' => now()->subHours(2),
        ]);

        WhatsappMessage::factory()->create([
            'task_id' => $completedTask->id,
            'direction' => 'inbound',
        ]);

        Task::factory()->create([
            'department_id' => $department->id,
            'category_id' => $category->id,
            'assigned_to_user_id' => $employee->id,
            'source' => TaskSource::MANUAL->value,
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => TaskPriority::URGENT->value,
            'due_at' => now()->subDay(),
        ]);

        Task::factory()->create([
            'status' => TaskStatus::CANCELLED->value,
        ]);

        $overview = app(TaskReportService::class)->getOverview(
            Task::query()->where('department_id', $department->id)
        );

        $this->assertSame(2, $overview['total_tasks']);
        $this->assertSame(1, $overview['completed_tasks']);
        $this->assertSame(1, $overview['in_progress_tasks']);
        $this->assertSame(1, $overview['overdue_tasks']);
        $this->assertSame(1, $overview['whatsapp_converted_tasks']);
        $this->assertTrue(is_float($overview['average_completion_seconds']) || is_int($overview['average_completion_seconds']));
    }

    public function test_it_returns_breakdown_lists(): void
    {
        $departmentA = Department::factory()->create(['name' => 'Maintenance']);
        $departmentB = Department::factory()->create(['name' => 'IT']);

        $categoryA = TaskCategory::factory()->create(['department_id' => $departmentA->id, 'name' => 'Electrical']);
        $categoryB = TaskCategory::factory()->create(['department_id' => $departmentB->id, 'name' => 'Network']);

        $employeeA = User::factory()->create(['name' => 'Employee A']);
        $employeeB = User::factory()->create(['name' => 'Employee B']);

        Task::factory()->create([
            'department_id' => $departmentA->id,
            'category_id' => $categoryA->id,
            'assigned_to_user_id' => $employeeA->id,
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::WHATSAPP->value,
        ]);

        Task::factory()->create([
            'department_id' => $departmentA->id,
            'category_id' => $categoryA->id,
            'assigned_to_user_id' => $employeeA->id,
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::ASSIGNED->value,
            'source' => TaskSource::MANUAL->value,
        ]);

        Task::factory()->create([
            'department_id' => $departmentB->id,
            'category_id' => $categoryB->id,
            'assigned_to_user_id' => $employeeB->id,
            'priority' => TaskPriority::LOW->value,
            'status' => TaskStatus::NEW->value,
            'source' => TaskSource::API->value,
        ]);

        $breakdowns = app(TaskReportService::class)->getBreakdowns(Task::query());

        $this->assertArrayHasKey('by_department', $breakdowns);
        $this->assertArrayHasKey('by_category', $breakdowns);
        $this->assertArrayHasKey('by_employee', $breakdowns);
        $this->assertArrayHasKey('by_priority', $breakdowns);
        $this->assertArrayHasKey('by_status', $breakdowns);
        $this->assertArrayHasKey('by_source', $breakdowns);

        $topDepartment = $breakdowns['by_department']->first();
        $this->assertNotNull($topDepartment);
        $this->assertSame('Maintenance', $topDepartment['label']);
        $this->assertSame(2, $topDepartment['total']);
    }
}
