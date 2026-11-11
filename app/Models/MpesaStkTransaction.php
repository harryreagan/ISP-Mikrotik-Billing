<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaStkTransaction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'gateway',
        'merchant_request_id',
        'checkout_request_id',
        'mpesa_receipt_number',
        'amount',
        'phone',
        'reference',
        'customer_unique_id',
        'result_code',
        'result_desc',
        'status',
        'raw_callback',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'result_code' => 'integer',
        'raw_callback' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_unique_id', 'customer_unique_id');
    }
}

