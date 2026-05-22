<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notifications\SendTestNotificationRequest;
use App\Models\User;
use App\Services\Notifications\FcmNotificationService;
use Illuminate\Http\JsonResponse;

class NotificationTestController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly FcmNotificationService $fcmNotificationService,
    ) {}

    public function store(SendTestNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $recipient = User::query()->findOrFail($validated['user_id']);

        $sent = $this->fcmNotificationService->sendTestNotification(
            recipient: $recipient,
            title: $validated['title'],
            body: $validated['body'],
            data: array_filter([
                'type' => 'test_notification',
                'task_id' => isset($validated['task_id']) ? (string) $validated['task_id'] : null,
            ]),
            deviceId: $validated['device_id'] ?? null,
        );

        if ($sent === 0) {
            return $this->errorResponse(
                message: 'The selected user does not have a registered device token.',
                status: 422,
                errors: ['device' => ['No registered device token was found for the selected user/device.']],
            );
        }

        return $this->successResponse(
            data: ['sent' => $sent],
            message: 'Test notification queued for delivery.',
        );
    }
}
