<?php

namespace App\Http\Requests\Api\V1\Accounts;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'string', 'exists:institutions,id'],
            'name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'string', 'in:bank,credit_card,e_wallet,cash,investment,loan'],
            'currency' => ['nullable', 'string', 'size:3'],
            'opening_balance' => ['required', 'numeric'],
            'account_number_masked' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
