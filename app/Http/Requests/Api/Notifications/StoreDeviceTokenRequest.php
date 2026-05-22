<?php

namespace App\Http\Requests\Api\Notifications;

use App\Http\Requests\Api\ApiFormRequest;

class StoreDeviceTokenRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fcm_token' => ['required', 'string', 'max:500'],
            'device_id' => ['required', 'string', 'max:120'],
            'device_type' => ['nullable', 'string', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
