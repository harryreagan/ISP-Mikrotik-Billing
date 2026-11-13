<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    /**
     * Explicit table name to avoid collision with legacy hotspot/pppoe customer bandwidth packages.
     */
    protected $table = 'platform_plans';

    protected $fillable = [
        'slug',
        'name',
        'base_price_kes',
        'router_limit',
        'customer_limit',
        'features',
        'is_popular',
        'badge',
        'button_text',
        'button_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'base_price_kes' => 'integer',
        'router_limit' => 'integer',
        'customer_limit' => 'integer',
        'features' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope only active subscription plans in display order.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Check if this is an enterprise / custom priced plan.
     */
    public function isCustom(): bool
    {
        return $this->base_price_kes === null;
    }
}
