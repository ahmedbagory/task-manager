<?php

namespace App\Http\Requests\WhatsApp;

use App\Services\WhatsApp\WhatsAppMediaService;
use Illuminate\Foundation\Http\FormRequest;

class SendWhatsappConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone', ''));

        $this->merge([
            'phone' => ltrim((string) $phone, '+'),
            'body' => trim((string) $this->input('body', '')),
            'group_id' => trim((string) $this->input('group_id', '')) ?: null,
            'group_name' => trim((string) $this->input('group_name', '')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:8', 'max:15'],
            'group_id' => ['nullable', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimetypes:'.implode(',', WhatsAppMediaService::allowedMimeTypes())],
        ];
    }

    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                $body = trim((string) $this->input('body', ''));
                $hasAttachment = $this->hasFile('attachment');

                if ($body === '' && ! $hasAttachment) {
                    $validator->errors()->add('body', __('Please provide a message or attach a file.'));
                }
            },
        ];
    }
}
