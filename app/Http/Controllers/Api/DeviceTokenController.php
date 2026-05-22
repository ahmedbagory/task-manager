<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notifications\DestroyDeviceTokenRequest;
use App\Http\Requests\Api\Notifications\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    use RespondsWithJson;

    /**
     * Register or update FCM token.
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DeviceToken::query()
            ->where('fcm_token', $validated['fcm_token'])
            ->where('device_id', '!=', $validated['device_id'])
            ->delete();

        DeviceToken::updateOrCreate(
            ['device_id' => $validated['device_id']],
            [
                'user_id' => $user->id,
                'fcm_token' => $validated['fcm_token'],
                'device_type' => $validated['device_type'] ?? 'android',
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return $this->successResponse(
            message: 'Device token registered.',
        );
    }

    /**
     * Unregister FCM token (on logout).
     */
    public function destroy(DestroyDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $deleted = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) use ($validated): void {
                if (! empty($validated['device_id'])) {
                    $query->orWhere('device_id', $validated['device_id']);
                }

                if (! empty($validated['fcm_token'])) {
                    $query->orWhere('fcm_token', $validated['fcm_token']);
                }
            })
            ->delete();

        return $this->successResponse(
            data: ['deleted' => $deleted],
            message: 'Device token removed.',
        );
    }
}
