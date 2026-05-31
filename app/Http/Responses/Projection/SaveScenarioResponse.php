<?php

namespace App\Http\Responses\Projection;

use App\Models\ProjectionScenario;

class SaveScenarioResponse
{
    public function __construct(
        private readonly string $message,
        private readonly ProjectionScenario $scenario,
        private readonly array $result,
    ) {
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'scenario' => ScenarioResponse::summary($this->scenario),
            'result' => $this->result,
        ];
    }
}
