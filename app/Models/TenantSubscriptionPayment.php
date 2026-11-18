<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscriptionPayment extends Model
{
    use HasFactory;

    protected $table = 'tenant_subscription_payments';

    protected $fillable = [
        'tenant_id',
        'plan_slug',
        'billing_cycle',
        'amount',
        'currency',
        'gateway',
        'reference',
        'external_reference',
        'phone',
        'email',
        'status',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}

