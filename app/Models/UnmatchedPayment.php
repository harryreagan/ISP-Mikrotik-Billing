<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnmatchedPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'sender',
        'transaction_code',
        'amount',
        'phone',
        'sender_name',
        'account_reference',
        'raw_message',
        'raw_payload',
        'status',
        'customer_id',
        'customer_unique_id',
        'assigned_by',
        'assigned_at',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_payload' => 'array',
        'assigned_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(CustomersInfo::class, 'customer_id');
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

