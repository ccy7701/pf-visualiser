<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectionActualMonth extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id',
        'month',
        'opening_coh',
        'net_income',
        'expenses',
        'debt_servicing',
        'closing_coh',
        'closing_elr',
        'closing_epf',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_coh' => 'decimal:2',
            'net_income' => 'decimal:2',
            'expenses' => 'decimal:2',
            'debt_servicing' => 'decimal:2',
            'closing_coh' => 'decimal:2',
            'closing_elr' => 'decimal:2',
            'closing_epf' => 'decimal:2',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ProjectionScenario::class, 'scenario_id');
    }
}
