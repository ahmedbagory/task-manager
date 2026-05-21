<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

class ManualInboundMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:100'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'body' => ['required_without:media_url', 'nullable', 'string'],
            'media_url' => ['nullable', 'string', 'max:2048'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'message_type' => ['nullable', 'string', 'max:100'],
            'provider_message_id' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable'],
            'source' => ['nullable', 'string', 'max:50'],
            'raw_payload' => ['nullable', 'array'],
        ];
    }
}
