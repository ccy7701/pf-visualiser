<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workday extends Model
{
    use HasFactory;

    public const STATUS_WORKDAY = 'workday';
    public const STATUS_ABSENCE = 'absence';
    public const STATUS_HOLIDAY = 'holiday';

    protected $fillable = [
        'date',
        'status',
        'notes',
        // kept for backward compatibility with existing rows/code paths
        'is_workday',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_workday' => 'boolean',
        ];
    }
}
