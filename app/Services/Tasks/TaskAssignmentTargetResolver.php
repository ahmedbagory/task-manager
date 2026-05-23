<?php

namespace App\Services\Tasks;

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskAssignmentTarget;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskAssignmentTargetResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{departments: array<int, int>, units: array<int, int>, users: array<int, int>, all: bool}
     */
    public function extractTargets(array &$data): array
    {
        $isAll = ! empty($data['assign_to_all']);
        $targets = $this->normalizeTargetBuckets($data);
        $targets['all'] = $isAll;

        unset(
            $data['assign_to_all'],
            $data['assignment_target_departments'],
            $data['assignment_target_units'],
            $data['assignment_target_users'],
            $data['department_ids'],
            $data['child_department_ids'],
            $data['user_ids'],
        );

        return $targets;
    }

    /**
     * @param  array{departments?: array<int, int>, units?: array<int, int>, users?: array<int, int>, all?: bool}  $targets
     * @return array{department_ids: array<int, int>, user_ids: array<int, int>, all: bool}
     */
    public function syncTargets(Task $task, array $targets, ?User $actor = null): array
    {
        $isAll = ! empty($targets['all']) || ! empty($targets['assign_to_all']);

        $task->assignmentTargets()->delete();

        if ($isAll) {
            TaskAssignmentTarget::query()->create([
                'task_id' => $task->id,
                'target_type' => 'all',
                'target_id' => 0,
                'assigned_by' => $actor?->id,
            ]);

            return ['department_ids' => [], 'user_ids' => [], 'all' => true];
        }

        $normalizedTargets = $this->normalizeTargetBuckets($targets);
        $departmentIds = $this->normalizeIds(array_merge($normalizedTargets['departments'], $normalizedTargets['units']));
        $userIds = $this->normalizeIds($normalizedTargets['users']);

        foreach ($departmentIds as $departmentId) {
            TaskAssignmentTarget::query()->create([
                'task_id' => $task->id,
                'target_type' => 'department',
                'target_id' => $departmentId,
                'assigned_by' => $actor?->id,
            ]);
        }

        foreach ($userIds as $userId) {
            TaskAssignmentTarget::query()->create([
                'task_id' => $task->id,
                'target_type' => 'user',
                'target_id' => $userId,
                'assigned_by' => $actor?->id,
            ]);
        }

        return [
            'department_ids' => $departmentIds,
            'user_ids' => $userIds,
            'all' => false,
        ];
    }

    /**
     * @param  array<int, int|string>  $departmentIds
     * @return array{departments: array<int, int>, units: array<int, int>}
     */
    public function splitDepartmentIds(array $departmentIds): array
    {
        $departments = Department::query()
            ->whereIn('id', $this->normalizeIds($departmentIds))
            ->get(['id', 'parent_id']);

        return [
            'departments' => $departments
                ->whereNull('parent_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'units' => $departments
                ->whereNotNull('parent_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     assign_to_all: bool,
     *     assignment_target_departments: array<int, int>,
     *     assignment_target_units: array<int, int>,
     *     assignment_target_users: array<int, int>
     * }
     */
    public function fillFormTargets(Task $task): array
    {
        $targets = $task->assignmentTargets()->get(['target_type', 'target_id']);

        $hasAll = $targets->contains('target_type', 'all');

        if ($hasAll) {
            return [
                'assign_to_all' => true,
                'assignment_target_departments' => [],
                'assignment_target_units' => [],
                'assignment_target_users' => [],
            ];
        }

        $departmentIds = $this->extractDepartmentIdsFromStoredTargets($targets);
        $split = $this->splitDepartmentIds($departmentIds);
        $userIds = $targets
            ->where('target_type', 'user')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'assign_to_all' => false,
            'assignment_target_departments' => $split['departments'],
            'assignment_target_units' => $split['units'],
            'assignment_target_users' => $userIds,
        ];
    }

    /**
     * @param  array{departments?: array<int, int|string>, units?: array<int, int|string>, users?: array<int, int|string>, all?: bool}  $targets
     * @return Collection<int, User>
     */
    public function resolveUsersFromTargets(array $targets): Collection
    {
        if (! empty($targets['all']) || ! empty($targets['assign_to_all'])) {
            return $this->resolveAllEligibleUsers();
        }

        $normalizedTargets = $this->normalizeTargetBuckets($targets);
        $targetedDepartmentIds = $this->resolveDepartmentAudienceIds(
            topLevelIds: $normalizedTargets['departments'],
            unitIds: $normalizedTargets['units'],
        );
        $directUserIds = $normalizedTargets['users'];

        $resolvedIds = [];

        if ($targetedDepartmentIds !== []) {
            $resolvedIds = array_merge(
                $resolvedIds,
                User::query()
                    ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                        Rbac::EMPLOYEE,
                        Rbac::SUPERVISOR,
                    ]))
                    ->whereHas('department', fn (Builder $query) => $query->where('is_active', true))
                    ->whereIn('department_id', $targetedDepartmentIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            );
        }

        if ($directUserIds !== []) {
            $resolvedIds = array_merge(
                $resolvedIds,
                User::query()
                    ->whereIn('id', $directUserIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            );
        }

        $resolvedIds = $this->normalizeIds($resolvedIds);

        if ($resolvedIds === []) {
            return collect();
        }

        return User::query()
            ->with(['department.parent'])
            ->whereIn('id', $resolvedIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveAllEligibleUsers(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                Rbac::EMPLOYEE,
                Rbac::SUPERVISOR,
            ]))
            ->with(['department.parent'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveUsersForTask(Task $task): Collection
    {
        $targets = $task->assignmentTargets()->get(['target_type', 'target_id']);

        if ($targets->contains('target_type', 'all')) {
            return $this->resolveAllEligibleUsers();
        }

        $departmentIds = $this->extractDepartmentIdsFromStoredTargets($targets);
        $split = $this->splitDepartmentIds($departmentIds);

        return $this->resolveUsersFromTargets([
            'departments' => $split['departments'],
            'units' => $split['units'],
            'users' => $targets
                ->where('target_type', 'user')
                ->pluck('target_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function assignmentOptionsForTask(?Task $task = null, ?int $excludeUserId = null): array
    {
        $query = User::query()
            ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('name', [
                Rbac::EMPLOYEE,
                Rbac::SUPERVISOR,
            ]))
            ->with(['department.parent'])
            ->orderBy('name');

        if ($task) {
            $resolvedUserIds = $this->resolveUsersForTask($task)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($resolvedUserIds !== []) {
                $query->whereIn('id', $resolvedUserIds);
            }
        }

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (User $user) => [
                $user->id => $user->name.($user->department ? " ({$user->department->hierarchy_name})" : ''),
            ])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $topLevelIds
     * @param  array<int, int|string>  $unitIds
     * @return array<int, int>
     */
    private function resolveDepartmentAudienceIds(array $topLevelIds, array $unitIds): array
    {
        $topLevelIds = $this->normalizeIds($topLevelIds);
        $unitIds = $this->normalizeIds($unitIds);

        $childIds = $topLevelIds === []
            ? []
            : Department::query()
                ->whereIn('parent_id', $topLevelIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        return $this->normalizeIds(array_merge($topLevelIds, $unitIds, $childIds));
    }

    /**
     * Normalize mixed legacy/current target payloads into department buckets.
     *
     * @param  array<string, mixed>  $targets
     * @return array{departments: array<int, int>, units: array<int, int>, users: array<int, int>}
     */
    private function normalizeTargetBuckets(array $targets): array
    {
        return [
            'departments' => $this->normalizeIds(array_merge(
                (array) ($targets['assignment_target_departments'] ?? []),
                (array) ($targets['departments'] ?? []),
                (array) ($targets['department_ids'] ?? []),
            )),
            'units' => $this->normalizeIds(array_merge(
                (array) ($targets['assignment_target_units'] ?? []),
                (array) ($targets['units'] ?? []),
                (array) ($targets['child_department_ids'] ?? []),
            )),
            'users' => $this->normalizeIds(array_merge(
                (array) ($targets['assignment_target_users'] ?? []),
                (array) ($targets['users'] ?? []),
                (array) ($targets['user_ids'] ?? []),
            )),
        ];
    }

    /**
     * @param  Collection<int, TaskAssignmentTarget>  $targets
     * @return array<int, int>
     */
    private function extractDepartmentIdsFromStoredTargets(Collection $targets): array
    {
        $candidateDepartmentIds = $targets
            ->where('target_type', 'department')
            ->pluck('target_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($candidateDepartmentIds === []) {
            return [];
        }

        return Department::query()
            ->whereIn('id', $this->normalizeIds($candidateDepartmentIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private function normalizeIds(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
