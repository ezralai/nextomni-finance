<?php

namespace App\Http\Requests\Api\V1\Transactions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'account_id' => [
                'required',
                'string',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable',
                'string',
                Rule::exists('transaction_categories', 'id')->where(function ($query) use ($userId) {
                    $query->whereNull('user_id');
                    if ($userId) {
                        $query->orWhere('user_id', $userId);
                    }
                }),
            ],
            'type' => ['required', 'string', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
            'transacted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:posted,pending,reconciled,void'],
        ];
    }
}
