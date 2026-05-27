<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalarySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'effective_from',
        'effective_until',
        'monthly_net_salary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'monthly_net_salary' => 'decimal:2',
        ];
    }
}
