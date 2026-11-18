<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Router extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'routers';

    protected $guarded = ['id'];

    protected $casts = [
        'services'                   => 'array',
        'port_roles'                 => 'array',
        'is_test'                    => 'boolean',
        'vpn_capable'                => 'boolean',
        'provision_token_expires_at' => 'datetime',
        'last_seen_at'               => 'datetime',
        'last_polled_at'             => 'datetime',
        'vpn_connected_at'           => 'datetime',
        'provisioned_at'             => 'datetime',
        'network_changed_at'         => 'datetime',
    ];

    protected static function booted()
    {
        static::saving(function ($router) {
            if (!empty($router->name) && empty($router->router_name)) {
                $router->router_name = $router->name;
            } elseif (!empty($router->router_name) && empty($router->name)) {
                $router->name = $router->router_name;
            }

            if (!empty($router->ip) && empty($router->ip_address)) {
                $router->ip_address = $router->ip;
            } elseif (!empty($router->ip_address) && empty($router->ip)) {
                $router->ip = $router->ip_address;
            }
        });
    }

    /**
     * Scope query to a specific tenant.
     */
    public function scopeForTenant($query, $tenantId = null)
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }

        $user = auth()->user();
        if ($user && !empty($user->tenant_id)) {
            return $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    /**
     * Helper to check online status.
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Mutator & Accessor for router_name compatibility.
     */
    public function getRouterNameAttribute()
    {
        return $this->name;
    }

    public function setRouterNameAttribute($value)
    {
        $this->attributes['name'] = $value;
    }

    /**
     * Mutator & Accessor for ip_address compatibility.
     */
    public function getIpAddressAttribute()
    {
        return $this->vpn_ip ?: $this->ip;
    }

    public function setIpAddressAttribute($value)
    {
        $this->attributes['ip'] = $value;
    }

    /**
     * Mutator & Accessor for action compatibility.
     */
    public function getActionAttribute()
    {
        return $this->status === 'online' ? 'connected' : 'disconnected';
    }

    public function setActionAttribute($value)
    {
        $this->attributes['status'] = ($value === 'connected' ? 'online' : 'offline');
    }
}

