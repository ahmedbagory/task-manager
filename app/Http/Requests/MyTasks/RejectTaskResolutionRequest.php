<?php

namespace App\Http\Requests\MyTasks;

use Illuminate\Foundation\Http\FormRequest;

class RejectTaskResolutionRequest extends FormRequest
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
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
