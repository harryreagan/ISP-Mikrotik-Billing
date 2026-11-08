<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HubOutbox extends Model
{
    use HasFactory;

    protected $table = 'hub_outbox';

    protected $fillable = [
        'endpoint',
        'method',
        'payload',
        'attempts',
        'status',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
    ];
}
