<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class IspSmsGateway extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'isp_sms_gateways';

    protected $guarded = ['id'];

    protected $casts = [
        'http_headers'          => 'array',
        'is_default'            => 'boolean',
        'is_fallback'           => 'boolean',
        'balance'               => 'float',
        'low_balance_threshold' => 'float',
        'last_tested_at'        => 'datetime',
    ];

    /**
     * Decrypt the stored API key server-side only.
     */
    public function decryptedApiKey(): string
    {
        if (empty($this->api_key)) {
            return '';
        }
        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Throwable $e) {
            return (string) $this->api_key;
        }
    }

    /**
     * Decrypt the stored API secret server-side only.
     */
    public function decryptedApiSecret(): string
    {
        if (empty($this->api_secret)) {
            return '';
        }
        try {
            return Crypt::decryptString($this->api_secret);
        } catch (\Throwable $e) {
            return (string) $this->api_secret;
        }
    }
}

