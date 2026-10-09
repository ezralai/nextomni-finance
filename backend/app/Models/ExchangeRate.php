<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'effective_date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'string',
            'effective_date' => 'date',
        ];
    }
}
