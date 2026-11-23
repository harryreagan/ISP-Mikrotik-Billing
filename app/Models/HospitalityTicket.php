<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalityTicket extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'isp_hospitality_tickets';

    protected $fillable = [
        'tenant_id',
        'ticket_code',
        'ticket_type',
        'source_system',
        'guest_name',
        'guest_phone',
        'room_number',
        'receipt_ref',
        'bill_amount',
        'duration_hours',
        'device_limit',
        'package_id',
        'router_id',
        'status',
        'first_used_at',
        'expires_at',
        'qr_url',
        'bound_mac',
    ];

    protected $casts = [
        'bill_amount'    => 'decimal:2',
        'duration_hours' => 'integer',
        'device_limit'   => 'integer',
        'first_used_at'  => 'datetime',
        'expires_at'     => 'datetime',
    ];

    public function package()
    {
        return $this->belongsTo(PackageList::class, 'package_id');
    }

    public function router()
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

