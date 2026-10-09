<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ScopedBy([TenantScope::class])]
class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'type', // income, expense, transfer
        'amount',
        'currency',
        'transacted_at',
        'description',
        'notes',
        'reference_number',
        'deduplication_hash',
        'status', // posted, pending, reconciled, void
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'string',
            'transacted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'category_id');
    }

    public function sourceTransfer(): HasOne
    {
        return $this->hasOne(Transfer::class, 'source_transaction_id');
    }

    public function destinationTransfer(): HasOne
    {
        return $this->hasOne(Transfer::class, 'destination_transaction_id');
    }

    public function getMoneyAmount(): Money
    {
        return Money::of($this->amount ?? '0.0000', $this->currency ?? 'MYR');
    }
}
