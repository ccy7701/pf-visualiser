<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectionResultCache extends Model
{
    use HasFactory;

    protected $table = 'projection_results_cache';

    protected $fillable = [
        'scenario_id',
        'results_json',
    ];

    protected function casts(): array
    {
        return [
            'results_json' => 'array',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ProjectionScenario::class, 'scenario_id');
    }
}
