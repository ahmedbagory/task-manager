<?php

namespace App\Http\Requests\Api\Notifications;

use App\Http\Requests\Api\ApiFormRequest;

class SendTestNotificationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('settings.api.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:240'],
        ];
    }
}
