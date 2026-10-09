<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amountMoney = Money::of($this->amount ?? '0.0000', $this->currency ?? 'MYR');
        $feeMoney = Money::of($this->fee ?? '0.0000', $this->currency ?? 'MYR');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'source_account_id' => $this->source_account_id,
            'destination_account_id' => $this->destination_account_id,
            'source_transaction_id' => $this->source_transaction_id,
            'destination_transaction_id' => $this->destination_transaction_id,
            'amount' => $amountMoney->toDecimal(),
            'fee' => $feeMoney->toDecimal(),
            'amount_formatted' => $amountMoney->format(),
            'fee_formatted' => $feeMoney->format(),
            'currency' => $this->currency,
            'transacted_at' => $this->transacted_at?->toIso8601String(),
            'notes' => $this->notes,
            'source_account' => new AccountResource($this->whenLoaded('sourceAccount')),
            'destination_account' => new AccountResource($this->whenLoaded('destinationAccount')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
