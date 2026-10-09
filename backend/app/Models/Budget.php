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
class Budget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'currency',
        'period',
        'start_date',
        'end_date',
        'rollover_enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'string',
            'start_date' => 'date',
            'end_date' => 'date',
            'rollover_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'category_id');
    }

    public function getMoneyAmount(): Money
    {
        return Money::of($this->amount ?? '0.0000', $this->currency ?? 'MYR');
    }
}
