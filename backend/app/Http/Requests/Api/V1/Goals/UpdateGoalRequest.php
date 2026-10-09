<?php

namespace App\Http\Requests\Api\V1\Goals;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['sometimes', 'numeric', 'gt:0'],
            'current_amount' => ['sometimes', 'numeric', 'gte:0'],
            'target_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'color' => ['sometimes', 'string', 'max:20'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:in_progress,achieved,paused'],
        ];
    }
}
