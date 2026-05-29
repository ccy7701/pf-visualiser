<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectionScenario extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parameters_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'parameters_json' => 'array',
        ];
    }

    public function resultCache(): HasOne
    {
        return $this->hasOne(ProjectionResultCache::class, 'scenario_id');
    }
}
