<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithJson
{
    /**
     * @param  array<string, mixed>|array<int, mixed>|null  $data
     * @param  array<string, mixed>  $meta
     */
    protected function successResponse(
        ?array $data = null,
        string $message = 'Request completed successfully.',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>|null  $errors
     */
    protected function errorResponse(
        string $message = 'Request failed.',
        int $status = 400,
        ?array $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
