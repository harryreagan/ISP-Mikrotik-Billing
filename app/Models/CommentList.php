<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentList extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'comment',
        'ip_address',
    ];
}
