<?php

namespace App\Http\Responses\Projection;

class DeleteScenarioResponse
{
    public function toArray(): array
    {
        return [
            'message' => 'Scenario deleted successfully.',
        ];
    }
}
