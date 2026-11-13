<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payable extends Model
{
    protected $fillable = [
        'month_session_id',
        'user_id',
        'amount',
        'paid_amount',
        'due_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    public function monthSession()
    {
        return $this->belongsTo(MonthSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('date', 'desc');
    }

    /**
     * Recalculate paid_amount, due_amount and status based on payments
     */
    public function recalculate(): void
    {
        $paid = (float)$this->payments()->sum('amount');
        $total = (float)$this->amount;
        $due = max(0, $total - $paid);

        $status = 'unpaid';
        if ($paid >= $total && $total > 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }

        $this->update([
            'paid_amount' => $paid,
            'due_amount'  => $due,
            'status'      => $status,
        ]);
    }
}
