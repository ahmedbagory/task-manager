<?php

namespace App\Http\Requests\MyTasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreTaskAttachmentRequest extends FormRequest
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
            'attachment' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'])
                    ->max(10 * 1024),
            ],
        ];
    }
}
