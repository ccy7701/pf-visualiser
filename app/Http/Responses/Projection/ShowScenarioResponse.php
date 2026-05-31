<?php

namespace App\Http\Responses\Projection;

use App\Models\ProjectionScenario;

class ShowScenarioResponse
{
    public function __construct(
        private readonly ProjectionScenario $scenario,
        private readonly array $payload,
        private readonly array $result,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scenario' => ScenarioResponse::detail($this->scenario, $this->payload),
            'result' => $this->result,
        ];
    }
}
