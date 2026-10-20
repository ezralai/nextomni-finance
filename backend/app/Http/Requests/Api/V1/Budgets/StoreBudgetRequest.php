<?php

namespace App\Http\Requests\Api\V1\Budgets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'category_id' => [
                'required',
                'string',
                Rule::exists('transaction_categories', 'id')->where(function ($query) use ($userId) {
                    $query->whereNull('user_id');
                    if ($userId) {
                        $query->orWhere('user_id', $userId);
                    }
                }),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period' => ['nullable', 'string', 'in:monthly,yearly,custom'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'rollover_enabled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
