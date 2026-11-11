<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationLogs extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'title',
        'message',
        'status',
        'type',
        'read_by',
        'read_at',
    ];
}
