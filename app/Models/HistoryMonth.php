<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryMonth extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'closing_coh',
        'expense_breakdown_json',
        'income_breakdown_json',
    ];

    protected function casts(): array
    {
        return [
            'closing_coh' => 'decimal:2',
            'expense_breakdown_json' => 'array',
            'income_breakdown_json' => 'array',
        ];
    }
}
