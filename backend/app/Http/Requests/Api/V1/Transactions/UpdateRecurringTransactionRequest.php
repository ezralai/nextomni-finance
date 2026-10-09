<?php

namespace App\Http\Requests\Api\V1\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'string', 'exists:accounts,id'],
            'category_id' => ['nullable', 'string', 'exists:transaction_categories,id'],
            'type' => ['sometimes', 'string', 'in:income,expense'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'description' => ['sometimes', 'string', 'max:255'],
            'frequency' => ['sometimes', 'string', 'in:daily,weekly,biweekly,monthly,quarterly,yearly'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'next_due_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'auto_post' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
