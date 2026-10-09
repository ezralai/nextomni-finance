<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringTransactionResource extends JsonResource
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
            'description' => $this->description,
            'notes' => $this->notes,
            'amount' => $amountMoney->toDecimal(),
            'amount_raw' => $this->amount,
            'amount_formatted' => $amountMoney->format(),
            'currency' => $this->currency,
            'frequency' => $this->frequency,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_due_date' => $this->next_due_date?->toDateString(),
            'last_posted_at' => $this->last_posted_at?->toIso8601String(),
            'auto_post' => $this->auto_post,
            'is_active' => $this->is_active,
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
