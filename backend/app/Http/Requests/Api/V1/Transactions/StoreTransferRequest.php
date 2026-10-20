<?php

namespace App\Http\Requests\Api\V1\Transactions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'source_account_id' => [
                'required',
                'string',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'destination_account_id' => [
                'required',
                'string',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
                'different:source_account_id',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'fee' => ['nullable', 'numeric', 'gte:0'],
            'transacted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
