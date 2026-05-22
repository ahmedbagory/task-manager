<?php

namespace App\Services\Notifications;

use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\MobileNotification;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MobileNotificationAudienceResolver
{
    /**
     * @param  array<string, mixed>  $targets
     * @return array{department_ids: array<int, int>, user_ids: array<int, int>}
     */
    public function selectedTargets(array $targets): array
    {
        return [
            'department_ids' => $this->normalizeIds(array_merge(
                (array) ($targets['target_department_ids'] ?? []),
                (array) ($targets['target_unit_ids'] ?? []),
                (array) ($targets['department_ids'] ?? []),
                (array) ($targets['unit_ids'] ?? []),
                (array) ($targets['departments'] ?? []),
                (array) ($targets['units'] ?? []),
            )),
            'user_ids' => $this->normalizeIds(array_merge(
                (array) ($targets['target_user_ids'] ?? []),
                (array) ($targets['user_ids'] ?? []),
                (array) ($targets['users'] ?? []),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $targets
     * @return Collection<int, User>
     */
    public function resolveUsers(array $targets): Collection
    {
        $selectedTargets = $this->selectedTargets($targets);
        $audienceDepartmentIds = $this->expandAudienceDepartmentIds($selectedTargets['department_ids']);
        $resolvedUserIds = [];

        if ($audienceDepartmentIds !== []) {
            $resolvedUserIds = array_merge(
                $resolvedUserIds,
                $this->eligibleUsersQuery()
                    ->whereHas('department', fn (Builder $query) => $query->where('is_active', true))
                    ->whereIn('department_id', $audienceDepartmentIds)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            );
        }

        if ($selectedTargets['user_ids'] !== []) {
            $resolvedUserIds = array_merge(
                $resolvedUserIds,
                $this->eligibleUsersQuery()
                    ->whereIn('id', $selectedTargets['user_ids'])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            );
        }

        $resolvedUserIds = $this->normalizeIds($resolvedUserIds);

        if ($resolvedUserIds === []) {
            return collect();
        }

        return User::query()
            ->with(['department.parent'])
            ->whereIn('id', $resolvedUserIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function resolveUsersForNotification(MobileNotification $mobileNotification): Collection
    {
        return $this->resolveUsers([
            'department_ids' => $mobileNotification->targets
                ->where('target_type', 'department')
                ->pluck('target_id')
                ->all(),
            'user_ids' => $mobileNotification->targets
                ->where('target_type', 'user')
                ->pluck('target_id')
                ->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $targets
     * @return array{
     *     users: Collection<int, User>,
     *     user_count: int,
     *     users_with_devices_count: int,
     *     device_count: int
     * }
     */
    public function preview(array $targets): array
    {
        $users = $this->resolveUsers($targets);
        $userIds = $users
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($userIds === []) {
            return [
                'users' => $users,
                'user_count' => 0,
                'users_with_devices_count' => 0,
                'device_count' => 0,
            ];
        }

        $deviceQuery = DeviceToken::query()->whereIn('user_id', $userIds);

        return [
            'users' => $users,
            'user_count' => $users->count(),
            'users_with_devices_count' => (clone $deviceQuery)->distinct()->count('user_id'),
            'device_count' => $deviceQuery->count(),
        ];
    }

    private function eligibleUsersQuery(): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                Rbac::EMPLOYEE,
                Rbac::SUPERVISOR,
            ]));
    }

    /**
     * @param  array<int, int|string>  $selectedDepartmentIds
     * @return array<int, int>
     */
    private function expandAudienceDepartmentIds(array $selectedDepartmentIds): array
    {
        $selectedDepartmentIds = $this->normalizeIds($selectedDepartmentIds);

        if ($selectedDepartmentIds === []) {
            return [];
        }

        $selectedDepartments = Department::query()
            ->active()
            ->whereIn('id', $selectedDepartmentIds)
            ->get(['id', 'parent_id']);

        $topLevelIds = $selectedDepartments
            ->whereNull('parent_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $childIds = $topLevelIds === []
            ? []
            : Department::query()
                ->active()
                ->whereIn('parent_id', $topLevelIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        return $this->normalizeIds(array_merge($selectedDepartmentIds, $childIds));
    }

    /**
     * @param  array<int, int|string>  $values
     * @return array<int, int>
     */
    private function normalizeIds(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
