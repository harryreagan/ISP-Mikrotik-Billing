<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use BelongsToTenant;
    protected $fillable = ['template', 'template_name', 'is_active', 'template_ex_en', 'template_ex_bn'];

    // public function isActive($query)
    // {
    //     return $query->where('is_active', 1);
    // }
}
