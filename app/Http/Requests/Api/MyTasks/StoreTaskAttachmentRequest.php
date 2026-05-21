<?php

namespace App\Http\Requests\Api\MyTasks;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rules\File;

class StoreTaskAttachmentRequest extends ApiFormRequest
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
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'])
                    ->max(10 * 1024),
            ],
        ];
    }
}
