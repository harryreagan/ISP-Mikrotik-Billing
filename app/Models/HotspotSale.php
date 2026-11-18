<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotSale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'router_id',
        'router_name',
        'voucher_code',
        'profile',
        'username',
        'phone',
        'mac_address',
        'ip_address',
        'amount',
        'payment_method',
        'status',
        'duration_minutes',
        'started_at',
        'expires_at',
        'note',
        'sync_error',
        'sale_date',
        'sold_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'sale_date'        => 'date',
        'started_at'       => 'datetime',
        'expires_at'       => 'datetime',
        'duration_minutes' => 'integer',
        'router_id'        => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    public function scopeForRouter($query, $router)
    {
        if (is_numeric($router)) {
            return $query->where('router_id', $router);
        }
        return $query->where('router_name', $router);
    }

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('sale_date', [$from, $to]);
    }
}
