<?php

namespace App\Http\Requests\Api\MyTasks;

use App\Http\Requests\Api\ApiFormRequest;

class RejectTaskResolutionRequest extends ApiFormRequest
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
