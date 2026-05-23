<?php

namespace App\Jobs;

use App\Enums\MobileNotificationRecipientStatus;
use App\Models\MobileNotification;
use App\Services\Notifications\FcmNotificationService;
use App\Services\Notifications\MobileNotificationAudienceResolver;
use App\Services\Notifications\MobileNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMobileNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $mobileNotificationId,
    ) {}

    public function handle(
        MobileNotificationAudienceResolver $mobileNotificationAudienceResolver,
        MobileNotificationService $mobileNotificationService,
        FcmNotificationService $fcmNotificationService,
    ): void {
        $mobileNotification = MobileNotification::query()
            ->with(['targets.target'])
            ->find($this->mobileNotificationId);

        if (! $mobileNotification) {
            return;
        }

        try {
            $users = $mobileNotificationAudienceResolver->resolveUsersForNotification($mobileNotification);
            $targetedUsersWithDevicesCount = 0;
            $targetedDevicesCount = 0;
            $deliveredDevicesCountTotal = 0;

            $mobileNotification->recipients()->delete();

            foreach ($users as $user) {
                $deviceCount = $fcmNotificationService->countTokensForUser($user);
                $deliveredDevicesCount = 0;
                $status = MobileNotificationRecipientStatus::SKIPPED_NO_DEVICE;
                $sentAt = null;

                if ($deviceCount > 0) {
                    $targetedUsersWithDevicesCount++;
                    $targetedDevicesCount += $deviceCount;
                    $deliveredDevicesCount = $fcmNotificationService->sendNotificationToUser(
                        recipient: $user,
                        title: $mobileNotification->title,
                        body: $mobileNotification->body,
                        data: [
                            'type' => 'manual_broadcast',
                            'mobile_notification_id' => (string) $mobileNotification->id,
                            'notification_id' => (string) $mobileNotification->id,
                            'route' => '/notifications/'.(string) $mobileNotification->id,
                            'has_attachments' => $mobileNotification->attachments()->exists() ? '1' : '0',
                        ],
                    );
                    $deliveredDevicesCountTotal += $deliveredDevicesCount;
                    $status = $deliveredDevicesCount > 0
                        ? MobileNotificationRecipientStatus::SENT
                        : MobileNotificationRecipientStatus::FAILED;
                    $sentAt = $deliveredDevicesCount > 0 ? now() : null;
                }

                $mobileNotification->recipients()->create([
                    'user_id' => $user->id,
                    'status' => $status,
                    'device_count' => $deviceCount,
                    'delivered_devices_count' => $deliveredDevicesCount,
                    'sent_at' => $sentAt,
                ]);
            }

            if ($targetedDevicesCount > 0 && $deliveredDevicesCountTotal === 0) {
                $mobileNotificationService->markAsFailed(
                    $mobileNotification,
                    'FCM rejected all targeted device tokens or delivery failed for every device.',
                );
            } else {
                $mobileNotificationService->markAsSent($mobileNotification, [
                    'targeted_users_count' => $users->count(),
                    'targeted_users_with_devices_count' => $targetedUsersWithDevicesCount,
                    'targeted_devices_count' => $targetedDevicesCount,
                ]);
            }
        } catch (\Throwable $throwable) {
            $mobileNotificationService->markAsFailed($mobileNotification, $throwable->getMessage());

            throw $throwable;
        }
    }
}
