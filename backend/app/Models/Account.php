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
class Account extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'institution_id',
        'name',
        'account_type',
        'currency',
        'opening_balance',
        'current_balance',
        'account_number_masked',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'string',
            'current_balance' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function getMoneyBalance(): Money
    {
        return Money::of($this->current_balance ?? '0.0000', $this->currency ?? 'MYR');
    }

    public function getMoneyOpeningBalance(): Money
    {
        return Money::of($this->opening_balance ?? '0.0000', $this->currency ?? 'MYR');
    }
}
