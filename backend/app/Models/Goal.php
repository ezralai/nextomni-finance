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
class Goal extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'description',
        'target_amount',
        'current_amount',
        'currency',
        'target_date',
        'color',
        'icon',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'string',
            'current_amount' => 'string',
            'target_date' => 'date',
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

    public function getFormattedTargetAmountAttribute(): string
    {
        return Money::of($this->target_amount, $this->currency)->format();
    }

    public function getFormattedCurrentAmountAttribute(): string
    {
        return Money::of($this->current_amount, $this->currency)->format();
    }
}
