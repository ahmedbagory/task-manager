<?php

namespace App\Http\Requests\Api\MyTasks;

use App\Enums\TaskStatus;
use App\Http\Requests\Api\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexMyTasksRequest extends ApiFormRequest
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
            'status' => ['nullable', Rule::in(TaskStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
