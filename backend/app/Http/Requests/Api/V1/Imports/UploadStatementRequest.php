<?php

namespace App\Http\Requests\Api\V1\Imports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'string',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()?->id),
            ],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }
}
