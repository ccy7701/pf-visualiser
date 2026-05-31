<?php

namespace App\Http\Responses\Projection;

use App\Models\ProjectionScenario;

class ScenarioResponse
{
    public static function summary(ProjectionScenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'notes' => $scenario->notes,
            'created_at' => $scenario->created_at?->toDateTimeString(),
            'updated_at' => $scenario->updated_at?->toDateTimeString(),
        ];
    }

    public static function detail(ProjectionScenario $scenario, array $payload): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'notes' => $scenario->notes,
            'parameters_json' => $payload,
            'created_at' => $scenario->created_at?->toDateTimeString(),
            'updated_at' => $scenario->updated_at?->toDateTimeString(),
        ];
    }

    public static function comparison(ProjectionScenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'notes' => $scenario->notes,
            'updated_at' => $scenario->updated_at?->toDateTimeString(),
        ];
    }
}
