<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amountMoney = Money::of($this->amount ?? '0.0000', $this->currency ?? 'MYR');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'type' => $this->type,
            'amount' => $amountMoney->toDecimal(),
            'amount_raw' => $this->amount,
            'amount_formatted' => $amountMoney->format(),
            'currency' => $this->currency,
            'transacted_at' => $this->transacted_at?->toIso8601String(),
            'description' => $this->description,
            'notes' => $this->notes,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
