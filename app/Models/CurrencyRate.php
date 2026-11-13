<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $table = 'currency_rates';

    protected $fillable = [
        'currency',
        'rate_from_kes',
        'symbol',
        'name',
        'country_codes',
        'sort_order',
    ];

    protected $casts = [
        'rate_from_kes' => 'float',
        'country_codes' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Scope ordered list with KES always first.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw("CASE WHEN currency = 'KES' THEN 0 ELSE 1 END")
                     ->orderBy('sort_order')
                     ->orderBy('currency');
    }
}
