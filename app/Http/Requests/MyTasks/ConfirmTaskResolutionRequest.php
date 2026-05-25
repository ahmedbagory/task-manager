<?php

namespace App\Http\Requests\MyTasks;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTaskResolutionRequest extends FormRequest
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
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
