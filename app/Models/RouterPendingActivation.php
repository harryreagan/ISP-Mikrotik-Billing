<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterPendingActivation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'router_id',
        'hotspot_sale_id',
        'voucher_code',
        'username',
        'password',
        'profile',
        'limit_uptime',
        'limit_bytes',
        'client_ip',
        'client_mac',
        'expires_at',
        'status',
        'attempts',
        'last_attempt_at',
        'last_error',
    ];

    protected $casts = [
        'expires_at'      => 'datetime',
        'last_attempt_at' => 'datetime',
        'attempts'        => 'integer',
        'limit_bytes'     => 'integer',
        'router_id'       => 'integer',
        'tenant_id'       => 'integer',
        'hotspot_sale_id' => 'integer',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    public function hotspotSale(): BelongsTo
    {
        return $this->belongsTo(HotspotSale::class, 'hotspot_sale_id');
    }
}

