<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workday extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'is_workday',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_workday' => 'boolean',
        ];
    }
}
