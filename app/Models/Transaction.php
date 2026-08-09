<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'datetime',
        'category_id',
        'subcategory_id',
        'note',
        'amount',
        'is_bnpl',
    ];

    protected function casts(): array
    {
        return [
            'datetime' => 'datetime',
            'amount' => 'decimal:2',
            'is_bnpl' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}
