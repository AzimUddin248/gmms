<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function meals()
    {
        return $this->hasMany(Meal::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }
}
