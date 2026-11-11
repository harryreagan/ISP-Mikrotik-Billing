<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerReview extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'ppp_user_id',
        'rating',
        'comment',
        'edit_count',
        'show_on_site',
    ];

    public function pppUser()
    {
        return $this->belongsTo(PPPSecrets::class, 'ppp_user_id', 'id');
    }
}
