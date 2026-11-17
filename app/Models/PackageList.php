<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageList extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'package', 'type', 'price', 'description', 'merchant_company',
        'plan_label', 'speed', 'speed_download', 'speed_download_unit', 'speed_upload', 'speed_upload_unit',
        'burst_speed', 'features', 'is_featured', 'show_on_site', 'is_active', 'show_public', 'sort_order',
        'mikrotik_rate_limit', 'push_to_mikrotik', 'mikrotik_local_address', 'mikrotik_remote_address',
        'router_name',
        'reseller_id',
        'validity_value', 'validity_unit', 'duration_value', 'duration_unit',
        'data_limit_mb', 'data_cap_mb', 'shared_users', 'validity_type',
    ];

    protected $casts = [
        'features'        => 'array',
        'is_featured'     => 'boolean',
        'show_on_site'    => 'boolean',
        'is_active'       => 'boolean',
        'show_public'     => 'boolean',
        'push_to_mikrotik'=> 'boolean',
        'price'           => 'decimal:2',
    ];

    /**
     * Map 'name' attribute to 'package' column for modern convention.
     */
    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['package'] ?? '');
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['package'] = $value;
    }

    /**
     * Scope query to a specific tenant.
     */
    public function scopeForTenant($query, $tenantId = null)
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
    }

    /**
     * Scope to active & publicly visible packages.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })->where(function ($q) {
            $q->where('show_on_site', true)->orWhere('show_public', true)->orWhereNull('show_on_site');
        });
    }

    /**
     * Many-to-many relationship with Routers.
     */
    public function routers()
    {
        return $this->belongsToMany(Router::class, 'package_router', 'package_id', 'router_id')
            ->withPivot('sync_status', 'sync_error', 'synced_at')
            ->withTimestamps();
    }

    public function router()
    {
        return $this->belongsTo(RouterList::class, 'router_name', 'router_name');
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }

    /**
     * Active customers subscribed to this package.
     */
    public function customers()
    {
        return $this->hasMany(CustomersInfo::class, 'package_id');
    }

    /**
     * Format Speed nicely, e.g. "10 Mbps"
     */
    public function getFormattedSpeedAttribute(): string
    {
        if (!empty($this->speed)) {
            return $this->speed;
        }

        $down = $this->speed_download ?: 5;
        $unit = $this->speed_download_unit ?: 'Mbps';
        return "{$down} {$unit}";
    }

    /**
     * Format Validity nicely, e.g. "1 Hour", "24 Hours", "30 Days"
     */
    public function getFormattedValidityAttribute(): string
    {
        $val = $this->validity_value ?: ($this->duration_value ?: 1);
        $unit = $this->validity_unit ?: ($this->duration_unit ?: 'hours');
        $unitClean = ucfirst($unit);
        if ($val == 1 && str_ends_with(strtolower($unitClean), 's')) {
            $unitClean = substr($unitClean, 0, -1);
        }
        return "{$val} {$unitClean}";
    }

    /**
     * Build RouterOS rate-limit string e.g. "5M/10M" (upload/download)
     */
    public function getRateLimitString(): string
    {
        if (!empty($this->mikrotik_rate_limit)) {
            return $this->mikrotik_rate_limit;
        }

        $up = (int) ($this->speed_upload ?: ($this->speed_download ?: 5));
        $upUnit = str_starts_with(strtoupper((string)$this->speed_upload_unit), 'K') ? 'k' : 'M';
        $down = (int) ($this->speed_download ?: 10);
        $downUnit = str_starts_with(strtoupper((string)$this->speed_download_unit), 'K') ? 'k' : 'M';

        return "{$up}{$upUnit}/{$down}{$downUnit}";
    }

    /**
     * Build RouterOS session-timeout string e.g. "1h", "24h", "30d", "15m"
     */
    public function getSessionTimeoutString(): string
    {
        $val = (int) ($this->validity_value ?: ($this->duration_value ?: 1));
        $unit = strtolower((string) ($this->validity_unit ?: ($this->duration_unit ?: 'hours')));

        return match ($unit) {
            'minute', 'minutes', 'm' => "{$val}m",
            'hour', 'hours', 'h'     => "{$val}h",
            'day', 'days', 'd'       => "{$val}d",
            'month', 'months'        => ($val * 30) . "d",
            default                  => "{$val}h",
        };
    }
}
