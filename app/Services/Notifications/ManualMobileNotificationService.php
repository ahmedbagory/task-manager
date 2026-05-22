<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentTargetResolver;
use Illuminate\Support\Collection;

class ManualMobileNotificationService
{
    public function __construct(
        private readonly TaskAssignmentTargetResolver $taskAssignmentTargetResolver,
        private readonly FcmNotificationService $fcmNotificationService,
    ) {}

    /**
     * @param  array{departments?: array<int, int|string>, units?: array<int, int|string>, users?: array<int, int|string>}  $targets
     * @return array{
     *     users: Collection<int, User>,
     *     user_count: int,
     *     users_with_devices_count: int,
     *     device_count: int
     * }
     */
    public function previewAudience(array $targets): array
    {
        $users = $this->taskAssignmentTargetResolver
            ->resolveUsersFromTargets($targets)
            ->unique('id')
            ->values();

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
            'users_with_devices_count' => (clone $deviceQuery)
                ->distinct()
                ->count('user_id'),
            'device_count' => $deviceQuery->count(),
        ];
    }

    /**
     * @param  array{departments?: array<int, int|string>, units?: array<int, int|string>, users?: array<int, int|string>}  $targets
     * @return array{
     *     users: Collection<int, User>,
     *     user_count: int,
     *     users_with_devices_count: int,
     *     device_count: int,
     *     targeted_users: int,
     *     targeted_users_with_devices: int,
     *     targeted_devices: int
     * }
     */
    public function send(string $title, string $body, array $targets, User $sender): array
    {
        $preview = $this->previewAudience($targets);
        $delivery = $this->fcmNotificationService->sendManualNotification(
            recipients: $preview['users'],
            title: $title,
            body: $body,
            data: [
                'type' => 'manual_broadcast',
                'sender_id' => (string) $sender->id,
            ],
        );

        return [
            ...$preview,
            ...$delivery,
        ];
    }
}
