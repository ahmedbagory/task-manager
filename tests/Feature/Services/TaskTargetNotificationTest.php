<?php

namespace Tests\Feature\Services;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\Task;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Services\Authorization\RbacInitializationService;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class TaskTargetNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        app(RbacInitializationService::class)->seed();
    }

    private function createEmployee(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Rbac::EMPLOYEE);

        return $user;
    }

    private function createTask(array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'status' => TaskStatus::PENDING_ASSIGNMENT->value,
            'source' => TaskSource::MANUAL->value,
        ], $attributes));
    }

    public function test_assigning_to_one_user_sends_one_notification(): void
    {
        $this->seedRoles();

        $employee = $this->createEmployee();
        DeviceToken::create([
            'user_id' => $employee->id,
            'device_id' => 'dev-1',
            'fcm_token' => 'token-1',
            'device_type' => 'android',
        ]);

        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employee, $task): void {
            $mock->shouldReceive('notifyTaskTargetsAssigned')
                ->once()
                ->withArgs(function (Task $t, array $userIds) use ($employee, $task): bool {
                    return $t->id === $task->id
                        && $userIds === [$employee->id];
                });
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'users' => [$employee->id],
        ]);
    }

    public function test_assigning_to_multiple_users_sends_notification_to_all(): void
    {
        $this->seedRoles();

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();
        $employeeC = $this->createEmployee();

        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employeeA, $employeeB, $employeeC, $task): void {
            $mock->shouldReceive('notifyTaskTargetsAssigned')
                ->once()
                ->withArgs(function (Task $t, array $userIds) use ($employeeA, $employeeB, $employeeC, $task): bool {
                    sort($userIds);
                    $expected = [$employeeA->id, $employeeB->id, $employeeC->id];
                    sort($expected);

                    return $t->id === $task->id && $userIds === $expected;
                });
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'users' => [$employeeA->id, $employeeB->id, $employeeC->id],
        ]);
    }

    public function test_assigning_to_all_sends_notification_to_all_active_users(): void
    {
        $this->seedRoles();

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole(Rbac::DISPATCHER);

        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employeeA, $employeeB, $task): void {
            $mock->shouldReceive('notifyTaskTargetsAssigned')
                ->once()
                ->withArgs(function (Task $t, array $userIds) use ($employeeA, $employeeB, $task): bool {
                    sort($userIds);
                    $expected = [$employeeA->id, $employeeB->id];
                    sort($expected);

                    return $t->id === $task->id && $userIds === $expected;
                });
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'all' => true,
        ]);
    }

    public function test_no_duplicate_notification_for_same_user(): void
    {
        $this->seedRoles();

        $employee = $this->createEmployee();

        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($employee, $task): void {
            $mock->shouldReceive('notifyTaskTargetsAssigned')
                ->once()
                ->withArgs(function (Task $t, array $userIds) use ($employee, $task): bool {
                    return $t->id === $task->id
                        && $userIds === [$employee->id];
                });
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'users' => [$employee->id, $employee->id, $employee->id],
        ]);
    }

    public function test_inactive_users_do_not_receive_notification(): void
    {
        $this->seedRoles();

        $activeEmployee = $this->createEmployee();

        $adminOnly = User::factory()->create();
        $adminOnly->assignRole(Rbac::ADMIN);

        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock) use ($activeEmployee, $task): void {
            $mock->shouldReceive('notifyTaskTargetsAssigned')
                ->once()
                ->withArgs(function (Task $t, array $userIds) use ($activeEmployee, $task): bool {
                    return $t->id === $task->id
                        && $userIds === [$activeEmployee->id];
                });
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'all' => true,
        ]);
    }

    public function test_no_all_target_type_row_is_created(): void
    {
        $this->seedRoles();

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyTaskTargetsAssigned');
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'all' => true,
        ]);

        $this->assertDatabaseMissing('task_assignment_targets', [
            'task_id' => $task->id,
            'target_type' => 'all',
        ]);

        $targets = TaskAssignmentTarget::where('task_id', $task->id)->get();
        $this->assertTrue($targets->count() >= 2);
        $targets->each(function (TaskAssignmentTarget $target): void {
            $this->assertTrue($target->isUserTarget());
        });
    }

    public function test_empty_targets_do_not_send_notification(): void
    {
        $this->seedRoles();
        $task = $this->createTask();

        $this->mock(FcmNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('notifyTaskTargetsAssigned');
        });

        app(TaskAssignmentTargetResolver::class)->syncTargets($task, [
            'users' => [],
            'departments' => [],
        ]);
    }
}
