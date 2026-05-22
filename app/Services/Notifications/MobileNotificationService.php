<?php

namespace App\Services\Notifications;

use App\Enums\MobileNotificationStatus;
use App\Jobs\SendMobileNotificationJob;
use App\Models\MobileNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileNotificationService
{
    public function __construct(
        private readonly MobileNotificationAudienceResolver $mobileNotificationAudienceResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     users: Collection<int, User>,
     *     user_count: int,
     *     users_with_devices_count: int,
     *     device_count: int
     * }
     */
    public function previewAudience(array $data): array
    {
        return $this->mobileNotificationAudienceResolver->preview($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createQueuedNotification(array $data, User $creator): MobileNotification
    {
        $preview = $this->previewAudience($data);

        if ($preview['user_count'] === 0) {
            throw ValidationException::withMessages([
                'target_department_ids' => __('No employees matched the selected audience.'),
            ]);
        }

        if ($preview['device_count'] === 0) {
            throw ValidationException::withMessages([
                'target_user_ids' => __('No registered mobile devices were found for the selected audience.'),
            ]);
        }

        $selectedTargets = $this->mobileNotificationAudienceResolver->selectedTargets($data);

        return DB::transaction(function () use ($creator, $data, $selectedTargets): MobileNotification {
            $mobileNotification = MobileNotification::query()->create([
                'title' => (string) $data['title'],
                'body' => (string) $data['body'],
                'status' => MobileNotificationStatus::QUEUED,
                'created_by' => $creator->id,
                'queued_at' => now(),
            ]);

            foreach ($selectedTargets['department_ids'] as $departmentId) {
                $mobileNotification->targets()->create([
                    'target_type' => 'department',
                    'target_id' => $departmentId,
                ]);
            }

            foreach ($selectedTargets['user_ids'] as $userId) {
                $mobileNotification->targets()->create([
                    'target_type' => 'user',
                    'target_id' => $userId,
                ]);
            }

            DB::afterCommit(fn () => SendMobileNotificationJob::dispatch($mobileNotification->id));

            return $mobileNotification;
        });
    }

    /**
     * @param  array{targeted_users_count: int, targeted_users_with_devices_count: int, targeted_devices_count: int}  $deliverySummary
     */
    public function markAsSent(MobileNotification $mobileNotification, array $deliverySummary): void
    {
        $mobileNotification->update([
            'status' => MobileNotificationStatus::SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'failure_message' => null,
            'targeted_users_count' => $deliverySummary['targeted_users_count'],
            'targeted_users_with_devices_count' => $deliverySummary['targeted_users_with_devices_count'],
            'targeted_devices_count' => $deliverySummary['targeted_devices_count'],
        ]);
    }

    public function markAsFailed(MobileNotification $mobileNotification, string $message): void
    {
        $mobileNotification->update([
            'status' => MobileNotificationStatus::FAILED,
            'failed_at' => now(),
            'failure_message' => $message,
        ]);
    }
}
