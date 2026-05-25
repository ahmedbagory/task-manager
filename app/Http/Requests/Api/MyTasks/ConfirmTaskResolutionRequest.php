<?php

namespace App\Http\Requests\Api\MyTasks;

use App\Http\Requests\Api\ApiFormRequest;

class ConfirmTaskResolutionRequest extends ApiFormRequest
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
