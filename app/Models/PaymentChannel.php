<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentChannel extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'isp_payment_channels';

    protected $fillable = [
        'user_id',
        'gateway',
        'channel_type',
        'channel_name',
        'payhero_channel_id',
        'short_code',
        'account_number',
        'bank_name',
        'bank_code',
        'api_key_encrypted',
        'api_secret_encrypted',
        'passkey_encrypted',
        'is_default',
        'is_active',
        'status',
        'last_tested_at',
        'test_result',
    ];

    protected $casts = [
        'is_default'     => 'boolean',
        'is_active'      => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function scopeForTenant($query, $tenantId = null)
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Virtual Accessor: till_number
     */
    public function getTillNumberAttribute(): ?string
    {
        return in_array($this->channel_type, ['till', 'buy_goods']) ? $this->short_code : null;
    }

    public function setTillNumberAttribute(?string $value): void
    {
        if (!empty($value)) {
            $this->attributes['short_code'] = trim($value);
        }
    }

    /**
     * Virtual Accessor: paybill_number
     */
    public function getPaybillNumberAttribute(): ?string
    {
        return in_array($this->channel_type, ['paybill', 'bank']) ? $this->short_code : null;
    }

    public function setPaybillNumberAttribute(?string $value): void
    {
        if (!empty($value)) {
            $this->attributes['short_code'] = trim($value);
        }
    }

    /**
     * Human-readable Type format matching POS
     */
    public function getFormattedTypeAttribute(): string
    {
        return match ($this->channel_type) {
            'buy_goods', 'till' => 'TILL NUMBER',
            'paybill'           => 'PAYBILL',
            'bank'              => 'BANK ACCOUNT',
            default             => strtoupper(str_replace('_', ' ', $this->channel_type ?? 'CHANNEL')),
        };
    }

    /**
     * Decrypt and return API key safely (never logged, never serialized).
     */
    public function getApiKey(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->api_key_encrypted);
        } catch (\Throwable $e) {
            return $this->api_key_encrypted;
        }
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = !empty($value) ? Crypt::encryptString(trim($value)) : null;
    }

    public function getApiSecret(): ?string
    {
        if (empty($this->api_secret_encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->api_secret_encrypted);
        } catch (\Throwable $e) {
            return $this->api_secret_encrypted;
        }
    }

    public function setApiSecretAttribute(?string $value): void
    {
        $this->attributes['api_secret_encrypted'] = !empty($value) ? Crypt::encryptString(trim($value)) : null;
    }

    public function getPasskey(): ?string
    {
        if (empty($this->passkey_encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($this->passkey_encrypted);
        } catch (\Throwable $e) {
            return $this->passkey_encrypted;
        }
    }

    public function setPasskeyAttribute(?string $value): void
    {
        $this->attributes['passkey_encrypted'] = !empty($value) ? Crypt::encryptString(trim($value)) : null;
    }

    /**
     * Mask sensitive credentials for UI display e.g. "ph_live_••••••"
     */
    public function getMaskedApiKeyAttribute(): string
    {
        $key = $this->getApiKey();
        if (empty($key)) {
            return 'Not configured';
        }
        if (strlen($key) <= 8) {
            return '••••••••';
        }
        return substr($key, 0, 4) . '••••••••' . substr($key, -4);
    }
}
