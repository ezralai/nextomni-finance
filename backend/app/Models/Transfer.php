<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class Transfer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'source_account_id',
        'destination_account_id',
        'source_transaction_id',
        'destination_transaction_id',
        'amount',
        'fee',
        'currency',
        'transacted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'string',
            'fee' => 'string',
            'transacted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }

    public function destinationTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'destination_transaction_id');
    }

    public function getMoneyAmount(): Money
    {
        return Money::of($this->amount ?? '0.0000', $this->currency ?? 'MYR');
    }

    public function getMoneyFee(): Money
    {
        return Money::of($this->fee ?? '0.0000', $this->currency ?? 'MYR');
    }
}
