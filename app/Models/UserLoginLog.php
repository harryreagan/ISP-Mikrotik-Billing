<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'authenticatable_id',
        'authenticatable_type',
        'username',
        'ip_address',
        'user_agent',
        'action',
    ];

    public function authenticatable()
    {
        return $this->morphTo();
    }
}
