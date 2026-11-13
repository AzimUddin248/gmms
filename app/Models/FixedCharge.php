<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedCharge extends Model
{
    protected $fillable = ['month_session_id', 'user_id', 'rent', 'water', 'servant', 'gas_electricity_internet'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
