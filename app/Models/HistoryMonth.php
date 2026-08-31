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
        'closing_elr',
        'closing_epf',
    ];

    protected function casts(): array
    {
        return [
            'closing_coh' => 'decimal:2',
            'closing_elr' => 'decimal:2',
            'closing_epf' => 'decimal:2',
        ];
    }
}
