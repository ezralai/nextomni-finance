<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class TransactionCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'slug',
        'icon',
        'color',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('is_active', true)->orderBy('name');
    }

    /**
     * Scope query to accessible categories (System global categories + user custom categories).
     */
    public function scopeAccessibleBy(Builder $query, ?string $userId = null): Builder
    {
        $resolvedUserId = $userId ?? Auth::id();

        return $query->where(function (Builder $q) use ($resolvedUserId) {
            $q->whereNull('user_id');
            if ($resolvedUserId) {
                $q->orWhere('user_id', $resolvedUserId);
            }
        });
    }

    /**
     * Scope query to top-level parent categories.
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
