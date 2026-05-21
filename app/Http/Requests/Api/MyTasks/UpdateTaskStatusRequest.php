<?php

namespace App\Http\Requests\Api\MyTasks;

use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends ApiFormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in([
                    'wait_response',
                    'waiting_response',
                    'in_progress',
                ]),
            ],
        ];
    }
}
