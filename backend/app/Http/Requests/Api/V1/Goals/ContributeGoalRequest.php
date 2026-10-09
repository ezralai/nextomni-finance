<?php

namespace App\Http\Requests\Api\V1\Goals;

use Illuminate\Foundation\Http\FormRequest;

class ContributeGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
