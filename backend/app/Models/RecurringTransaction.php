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
class RecurringTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'type', // income, expense
        'description',
        'notes',
        'amount',
        'currency',
        'frequency', // daily, weekly, biweekly, monthly, quarterly, yearly
        'start_date',
        'end_date',
        'next_due_date',
        'last_posted_at',
        'auto_post',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'string',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_due_date' => 'date',
            'last_posted_at' => 'datetime',
            'auto_post' => 'boolean',
            'is_active' => 'boolean',
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

    public function getFormattedAmountAttribute(): string
    {
        return Money::of($this->amount, $this->currency)->format();
    }
}
