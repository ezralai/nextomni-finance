<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentBalanceMoney = Money::of($this->current_balance ?? '0.0000', $this->currency ?? 'MYR');
        $openingBalanceMoney = Money::of($this->opening_balance ?? '0.0000', $this->currency ?? 'MYR');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'institution_id' => $this->institution_id,
            'name' => $this->name,
            'account_type' => $this->account_type,
            'currency' => $this->currency,
            'opening_balance' => $openingBalanceMoney->toDecimal(),
            'current_balance' => $currentBalanceMoney->toDecimal(),
            'current_balance_formatted' => $currentBalanceMoney->format(),
            'account_number_masked' => $this->account_number_masked,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'institution' => new InstitutionResource($this->whenLoaded('institution')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
