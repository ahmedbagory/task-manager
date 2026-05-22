<?php

namespace App\Http\Requests\Api\Notifications;

use App\Http\Requests\Api\ApiFormRequest;

class DestroyDeviceTokenRequest extends ApiFormRequest
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
            'fcm_token' => ['nullable', 'string', 'max:500', 'required_without:device_id'],
            'device_id' => ['nullable', 'string', 'max:120', 'required_without:fcm_token'],
        ];
    }
}
