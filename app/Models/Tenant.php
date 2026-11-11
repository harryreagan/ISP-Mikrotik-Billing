<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'subdomain',
        'billing_code',
        'country',
        'county',
        'is_active',
        'sms_balance',
        'email',
        'phone',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'sms_balance' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function routers(): HasMany
    {
        return $this->hasMany(Router::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(PackageList::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(CustomersInfo::class);
    }

    public function smsWallet(): HasOne
    {
        return $this->hasOne(SmsWallet::class, 'tenant_id');
    }

    public function paymentChannels(): HasMany
    {
        return $this->hasMany(PaymentChannel::class, 'tenant_id');
    }
}

