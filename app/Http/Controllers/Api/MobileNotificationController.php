<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MobileNotificationMediaResource;
use App\Models\MobileNotification;
use App\Models\MobileNotificationRecipient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileNotificationController extends Controller
{
    use RespondsWithJson;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recipients = MobileNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->with(['mobileNotification' => fn ($q) => $q->with(['attachments'])->withCount('attachments')])
            ->orderByDesc('created_at')
            ->paginate((int) ($request->query('per_page', 20)));

        $items = collect($recipients->items())
            ->filter(fn (MobileNotificationRecipient $r) => $r->mobileNotification !== null)
            ->map(function (MobileNotificationRecipient $recipient): array {
                $notification = $recipient->mobileNotification;
                $firstImage = $notification->attachments
                    ->first(fn ($attachment): bool => $attachment->isImage());

                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'read_at' => $recipient->read_at?->toIso8601String(),
                    'attachment_count' => $notification->attachments_count ?? 0,
                    'media_count' => $notification->attachments_count ?? 0,
                    'first_image_url' => $firstImage
                        ? route('api.mobile.notifications.attachments.download', [
                            'notification' => $notification->id,
                            'attachment' => $firstImage->id,
                        ])
                        : null,
                ];
            })
            ->values()
            ->all();

        return $this->successResponse(
            data: ['notifications' => $items],
            message: 'Notifications fetched successfully.',
            meta: [
                'pagination' => [
                    'current_page' => $recipients->currentPage(),
                    'last_page' => $recipients->lastPage(),
                    'per_page' => $recipients->perPage(),
                    'total' => $recipients->total(),
                ],
            ],
        );
    }

    public function show(Request $request, int $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recipient = MobileNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->where('mobile_notification_id', $notification)
            ->first();

        if (! $recipient) {
            return $this->errorResponse(message: 'Notification not found.', status: 404);
        }

        $notificationModel = MobileNotification::query()
            ->with('attachments')
            ->find($notification);

        if (! $notificationModel) {
            return $this->errorResponse(message: 'Notification not found.', status: 404);
        }

        if (! $recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }

        $media = MobileNotificationMediaResource::collection($notificationModel->attachments)->resolve();

        return $this->successResponse(
            data: [
                'notification' => [
                    'id' => $notificationModel->id,
                    'title' => $notificationModel->title,
                    'body' => $notificationModel->body,
                    'created_at' => $notificationModel->created_at?->toIso8601String(),
                    'read_at' => $recipient->read_at?->toIso8601String(),
                    'media' => $media,
                    'attachments' => $media,
                ],
            ],
            message: 'Notification fetched successfully.',
        );
    }

    public function downloadAttachment(Request $request, int $notification, int $attachment): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $isRecipient = MobileNotificationRecipient::query()
            ->where('user_id', $user->id)
            ->where('mobile_notification_id', $notification)
            ->exists();

        if (! $isRecipient) {
            return $this->errorResponse(message: 'Notification not found.', status: 404);
        }

        $attachmentModel = MobileNotificationAttachment::query()
            ->where('mobile_notification_id', $notification)
            ->where('id', $attachment)
            ->first();

        if (! $attachmentModel) {
            return $this->errorResponse(message: 'Attachment not found.', status: 404);
        }

        $disk = Storage::disk($attachmentModel->disk);

        if (! $disk->exists($attachmentModel->path)) {
            return $this->errorResponse(message: 'File not found on storage.', status: 404);
        }

        return $disk->download(
            $attachmentModel->path,
            $attachmentModel->original_name ?: basename($attachmentModel->path),
        );
    }
}
