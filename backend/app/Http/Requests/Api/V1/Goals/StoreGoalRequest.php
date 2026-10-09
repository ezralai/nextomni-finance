<?php

namespace App\Http\Requests\Api\V1\Goals;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['required', 'numeric', 'gt:0'],
            'current_amount' => ['nullable', 'numeric', 'gte:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'target_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'string', 'exists:accounts,id'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:in_progress,achieved,paused'],
        ];
    }
}
