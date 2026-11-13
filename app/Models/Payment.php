<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payable_id',
        'month_session_id',
        'user_id',
        'amount',
        'date',
        'payment_method',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date'   => 'date',
        ];
    }

    public function payable()
    {
        return $this->belongsTo(Payable::class);
    }

    public function monthSession()
    {
        return $this->belongsTo(MonthSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
