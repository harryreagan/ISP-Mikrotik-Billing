<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HospitalitySetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'isp_hospitality_settings';

    protected $fillable = [
        'tenant_id',
        'business_name',
        'api_key',
        'portal_token',
        'is_enabled',
        'default_hotel_package_id',
        'hotel_duration_hours',
        'hotel_device_limit',
        'allow_room_guest_login',
        'default_pos_package_id',
        'pos_min_spend',
        'pos_duration_hours',
        'pos_device_limit',
        'receipt_header',
        'receipt_footer',
        'selected_router_id',
        'webhook_secret',
    ];

    protected $casts = [
        'is_enabled'             => 'boolean',
        'allow_room_guest_login' => 'boolean',
        'hotel_duration_hours'   => 'integer',
        'hotel_device_limit'     => 'integer',
        'pos_min_spend'          => 'decimal:2',
        'pos_duration_hours'     => 'integer',
        'pos_device_limit'       => 'integer',
    ];

    /**
     * Get or create active settings for a tenant.
     */
    public static function forTenant(?int $tenantId = null): self
    {
        $tenantId = $tenantId ?: (auth()->user()->tenant_id ?? 1);

        $setting = static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'api_key'                 => 'hsm_live_' . Str::random(32),
                'portal_token'            => 'hsp_' . Str::random(32),
                'business_name'           => 'Hotel & Cafe Partner',
                'is_enabled'              => true,
                'hotel_duration_hours'    => 24,
                'hotel_device_limit'      => 3,
                'allow_room_guest_login'  => true,
                'pos_min_spend'           => 100.00,
                'pos_duration_hours'      => 2,
                'pos_device_limit'        => 1,
                'receipt_header'          => 'COMPLIMENTARY GUEST WI-FI',
                'receipt_footer'          => 'Scan QR code with phone camera to connect',
                'webhook_secret'          => 'whsec_' . Str::random(24),
            ]
        );

        if (empty($setting->portal_token)) {
            $setting->update(['portal_token' => 'hsp_' . Str::random(32)]);
        }

        return $setting;
    }

    public function hotelPackage()
    {
        return $this->belongsTo(PackageList::class, 'default_hotel_package_id');
    }

    public function posPackage()
    {
        return $this->belongsTo(PackageList::class, 'default_pos_package_id');
    }

    public function router()
    {
        return $this->belongsTo(Router::class, 'selected_router_id');
    }
}

