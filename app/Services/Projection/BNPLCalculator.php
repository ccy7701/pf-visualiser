<?php

namespace App\Services\Projection;

class BNPLCalculator
{
    public function repaymentForMonth(string $month, array $bnplEntries): float
    {
        $total = 0.0;

        foreach ($bnplEntries as $entry) {
            $entryMonth = $entry['month'] ?? null;

            if (! $entryMonth || $entryMonth !== $month) {
                continue;
            }

            $total += (float) ($entry['amount'] ?? 0);
        }

        return $total;
    }
}
