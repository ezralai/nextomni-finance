<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency ?? 'MYR';
        $targetMoney = Money::of($this->target_amount ?? '0.0000', $currency);
        $currentMoney = Money::of($this->current_amount ?? '0.0000', $currency);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'account_id' => $this->account_id,
            'name' => $this->name,
            'description' => $this->description,
            'target_amount' => $targetMoney->toDecimal(),
            'target_amount_raw' => $this->target_amount,
            'target_amount_formatted' => $targetMoney->format(),
            'current_amount' => $currentMoney->toDecimal(),
            'current_amount_raw' => $this->current_amount,
            'current_amount_formatted' => $currentMoney->format(),
            'currency' => $currency,
            'target_date' => $this->target_date?->toDateString(),
            'color' => $this->color,
            'icon' => $this->icon,
            'status' => $this->status,
            'account' => new AccountResource($this->whenLoaded('account')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
