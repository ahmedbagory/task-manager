<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    use RespondsWithJson;

    /**
     * Register or update FCM token.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
            'device_type' => 'sometimes|string|in:android,ios',
        ]);

        $user = $request->user();

        DeviceToken::updateOrCreate(
            ['fcm_token' => $request->input('fcm_token')],
            [
                'user_id' => $user->id,
                'device_type' => $request->input('device_type', 'android'),
            ],
        );

        return $this->successResponse(
            message: 'Device token registered.',
        );
    }

    /**
     * Unregister FCM token (on logout).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        DeviceToken::where('fcm_token', $request->input('fcm_token'))->delete();

        return $this->successResponse(
            message: 'Device token removed.',
        );
    }
}
