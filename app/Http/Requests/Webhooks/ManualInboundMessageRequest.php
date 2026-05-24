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
            'body' => ['nullable', 'string'],
            'media_url' => ['nullable', 'string', 'max:2048'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'message_type' => ['nullable', 'string', 'max:100'],
            'provider_message_id' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable'],
            'source' => ['nullable', 'string', 'max:50'],
            'raw_payload' => ['nullable', 'array'],
            'media' => ['nullable', 'array'],
            'media.has_media' => ['nullable', 'boolean'],
            'media.rejected' => ['nullable', 'boolean'],
            'media.type' => ['nullable', 'string', 'max:50'],
            'media.mime_type' => ['nullable', 'string', 'max:255'],
            'media.file_name' => ['nullable', 'string', 'max:255'],
            'media.original_name' => ['nullable', 'string', 'max:255'],
            'media.size' => ['nullable', 'integer', 'min:0'],
            'media.url' => ['nullable', 'string', 'max:2048'],
            'media.path' => ['nullable', 'string', 'max:2048'],
            'media.reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                $body = trim((string) $this->input('body', ''));
                $mediaUrl = trim((string) $this->input('media_url', ''));
                $hasMedia = filter_var($this->input('media.has_media', false), FILTER_VALIDATE_BOOL);

                if ($body === '' && $mediaUrl === '' && $hasMedia !== true) {
                    $validator->errors()->add('body', __('The inbound WhatsApp payload must contain text or media.'));
                }
            },
        ];
    }
}
