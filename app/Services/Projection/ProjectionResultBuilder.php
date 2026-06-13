<?php

namespace App\Services\Projection;

class ProjectionResultBuilder
{
    public function build(array $meta, array $months): array
    {
        return [
            'meta' => $meta,
            'months' => $months,
            'summary' => $this->buildSummary($months),
        ];
    }

    private function buildSummary(array $months): array
    {
        if (empty($months)) {
            return [
                'final_coh' => 0.0,
                'final_elr' => 0.0,
                'final_epf' => 0.0,
                'final_tfp' => 0.0,
                'lowest_coh' => 0.0,
                'highest_coh' => 0.0,
            ];
        }

        $final = $months[count($months) - 1];
        $cohValues = array_map(fn (array $row) => (float) $row['closing_coh'], $months);

        return [
            'final_coh' => (float) $final['closing_coh'],
            'final_elr' => (float) $final['closing_elr'],
            'final_epf' => (float) $final['closing_epf'],
            'final_tfp' => (float) $final['closing_coh'] + (float) $final['closing_elr'] + (float) $final['closing_epf'],
            'lowest_coh' => min($cohValues),
            'highest_coh' => max($cohValues),
        ];
    }
}
