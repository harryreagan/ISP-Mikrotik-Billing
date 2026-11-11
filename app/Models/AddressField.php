<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddressField extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['label', 'input_type', 'dropdown_list', 'required', 'print_preview', 'complain_preview', 'order'];
}
