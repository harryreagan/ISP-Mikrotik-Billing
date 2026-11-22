<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepaidUser extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'username',
        'password',
        'phone',
        'mac_address',
        'ip_address',
        'package_id',
        'plan_name',
        'type',
        'router_id',
        'router_name',
        'roaming_router_id',
        'roaming_router_name',
        'is_roaming',
        'method',
        'receipt_code',
        'amount',
        'status',
        'is_online',
        'was_connected',
        'reconnection_sms_sent',
        'reconnection_sms_sent_at',
        'disconnection_reason',
        'fup_enabled',
        'fup_limit_mb',
        'daily_data_bytes',
        'total_data_bytes',
        'last_seen_at',
        'started_at',
        'expires_at',
        'validity_expires_at',
        'total_duration_seconds',
        'used_seconds',
        'hotspot_sale_id',
        'customer_id',
        'ppp_secret_id',
    ];

    protected $casts = [
        'tenant_id'              => 'integer',
        'package_id'             => 'integer',
        'router_id'              => 'integer',
        'roaming_router_id'      => 'integer',
        'is_roaming'             => 'boolean',
        'amount'                 => 'decimal:2',
        'is_online'              => 'boolean',
        'was_connected'          => 'boolean',
        'reconnection_sms_sent'  => 'boolean',
        'reconnection_sms_sent_at' => 'datetime',
        'fup_enabled'            => 'boolean',
        'fup_limit_mb'           => 'integer',
        'daily_data_bytes'       => 'integer',
        'total_data_bytes'       => 'integer',
        'last_seen_at'           => 'datetime',
        'started_at'             => 'datetime',
        'expires_at'             => 'datetime',
        'validity_expires_at'    => 'datetime',
        'total_duration_seconds' => 'integer',
        'used_seconds'           => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(PackageList::class, 'package_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    public function roamingRouter(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'roaming_router_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_id');
    }

    public function getFormattedDailyDataAttribute(): string
    {
        $bytes = (int) ($this->daily_data_bytes ?? 0);
        if ($bytes <= 0) {
            return '0 KB';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1073741824, 2) . ' GB';
    }

    public function getRemainingSecondsAttribute(): int
    {
        if (!$this->total_duration_seconds) {
            return 0;
        }
        return max(0, $this->total_duration_seconds - ($this->used_seconds ?: 0));
    }

    public function getFormattedUsedDurationAttribute(): string
    {
        $sec = (int) ($this->used_seconds ?: 0);
        if ($sec <= 0) return '0s';
        if ($sec < 60) return "{$sec}s";
        $mins = floor($sec / 60);
        if ($mins < 60) return "{$mins}m";
        $hrs = floor($mins / 60);
        $remMins = $mins % 60;
        return $remMins > 0 ? "{$hrs}h {$remMins}m" : "{$hrs}h";
    }

    public function getFormattedRemainingDurationAttribute(): string
    {
        $sec = $this->remaining_seconds;
        if ($sec <= 0) return '0s';
        if ($sec < 60) return "{$sec}s";
        $mins = floor($sec / 60);
        if ($mins < 60) return "{$mins}m";
        $hrs = floor($mins / 60);
        $remMins = $mins % 60;
        return $remMins > 0 ? "{$hrs}h {$remMins}m" : "{$hrs}h";
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'disabled') {
            return 'disabled';
        }
        if ($this->status === 'expired') {
            return 'expired';
        }
        if ($this->type === 'hotspot' && $this->total_duration_seconds > 0) {
            if ($this->used_seconds >= $this->total_duration_seconds) {
                return 'expired';
            }
            if ($this->validity_expires_at && $this->validity_expires_at->isPast()) {
                return 'expired';
            }
        } elseif ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->is_online || $this->status === 'online') {
            return 'online';
        }
        return 'offline';
    }

    public static function upsertFromHotspotSale(
        HotspotSale $sale,
        ?PackageList $package = null,
        ?string $method = null
    ): self {
        $tenantId = (int) ($sale->tenant_id ?: 1);
        $username = strtoupper(trim((string) ($sale->voucher_code ?: $sale->username)));
        $pkg = $package;
        if (!$pkg && !empty($sale->profile)) {
            $cleanProfile = trim((string) $sale->profile);
            $stripped = preg_replace('/^(hs_|ppp_)/', '', $cleanProfile);
            $pkg = PackageList::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($cleanProfile, $stripped) {
                    $q->whereRaw('LOWER(TRIM(package)) = ?', [strtolower($cleanProfile)])
                      ->orWhereRaw('LOWER(TRIM(package)) = ?', [strtolower($stripped)]);
                })
                ->first();
        }

        $durSec = (int) ($sale->duration_seconds ?: (($sale->duration_minutes ?: 0) * 60));
        if ($durSec <= 0 && $pkg) {
            $parsed = \App\Services\MikrotikDuration::parse($pkg->validity ?? $pkg->package);
            $durSec = (int) ($parsed['seconds'] ?? 3600);
        }

        $usedSec = (int) ($sale->used_seconds ?: 0);
        $valExp = $sale->validity_expires_at ?: ($sale->expires_at ? Carbon::parse($sale->expires_at) : null);

        $isExpired = ($sale->status === 'expired');
        if (!$isExpired && $durSec > 0 && $usedSec >= $durSec) {
            $isExpired = true;
        }
        if (!$isExpired && $valExp && $valExp->isPast()) {
            $isExpired = true;
        }

        $methodText = $method ?: ('Mpesa STK' . ($sale->voucher_code ? ' + ' . strtoupper($sale->voucher_code) : ''));

        return self::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'username'  => $username,
                'type'      => 'hotspot',
            ],
            [
                'password'               => $username,
                'phone'                  => $sale->phone,
                'mac_address'            => $sale->mac_address ? strtoupper($sale->mac_address) : null,
                'ip_address'             => $sale->ip_address,
                'package_id'             => $pkg?->id,
                'plan_name'              => $pkg?->package ?: ($sale->profile ?: null),
                'router_id'              => $sale->router_id,
                'router_name'            => $sale->router_name,
                'method'                 => $methodText,
                'receipt_code'           => $sale->voucher_code ? strtoupper($sale->voucher_code) : null,
                'amount'                 => (float) $sale->amount,
                'status'                 => $isExpired ? 'expired' : 'active',
                'is_online'              => !$isExpired,
                'was_connected'          => !empty($sale->mac_address) || !empty($sale->ip_address),
                'disconnection_reason'   => $isExpired ? 'expired' : null,
                'fup_enabled'            => !empty($pkg?->data_limit_mb),
                'fup_limit_mb'           => $pkg?->data_limit_mb,
                'started_at'             => $sale->started_at ?: now(),
                'expires_at'             => $sale->expires_at,
                'validity_expires_at'    => $valExp ?: now()->addDays(30),
                'total_duration_seconds' => $durSec ?: 3600,
                'used_seconds'           => $usedSec,
                'hotspot_sale_id'        => $sale->id,
            ]
        );
    }

    public static function upsertFromCustomer(
        CustomersInfo $customer,
        ?PPPSecrets $secret = null,
        ?PackageList $package = null,
        ?Carbon $expiresAt = null
    ): self {
        $tenantId = (int) ($customer->tenant_id ?: 1);
        $secret = $secret ?: $customer->pppUser;
        $pkg = $package ?: ($customer->package && (int) $customer->package->tenant_id === $tenantId ? $customer->package : null);
        $type = in_array($customer->service_type, ['hotspot', 'pppoe', 'static'], true)
            ? $customer->service_type
            : ($pkg?->type ?: ($secret?->service ?: 'pppoe'));
        $username = trim((string) ($secret?->username ?: $customer->customer_unique_id));

        $router = $customer->router_id
            ? Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($customer->router_id)
            : null;

        $isDisabled = in_array($customer->status, ['disable', 'suspended', 'inactive'], true);
        $isExpired = !$isDisabled && $expiresAt && $expiresAt->isPast();

        return self::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'username'  => $username,
                'type'      => $type,
            ],
            [
                'password'             => $secret?->password,
                'phone'                => $customer->mobile,
                'mac_address'          => $secret?->caller_id ? strtoupper($secret->caller_id) : null,
                'ip_address'           => $secret?->ppp_remote_ip,
                'package_id'           => $pkg?->id,
                'plan_name'            => $pkg?->package,
                'router_id'            => $customer->router_id ?: $secret?->router_id,
                'router_name'          => $router?->name ?: $secret?->router_name,
                'method'               => 'Customer Account',
                'amount'               => (float) ($pkg?->price ?? 0),
                'status'               => $isDisabled ? 'disabled' : ($isExpired ? 'expired' : 'offline'),
                'disconnection_reason' => $isDisabled ? 'disabled' : ($isExpired ? 'expired' : null),
                'fup_enabled'          => !empty($pkg?->data_limit_mb),
                'fup_limit_mb'         => $pkg?->data_limit_mb,
                'started_at'           => $customer->connection_date ? Carbon::parse($customer->connection_date) : now(),
                'expires_at'           => $expiresAt,
                'customer_id'          => $customer->id,
                'ppp_secret_id'        => $secret?->id,
            ]
        );
    }
}

