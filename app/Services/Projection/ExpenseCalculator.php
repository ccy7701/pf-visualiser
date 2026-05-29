<?php

namespace App\Services\Projection;

class ExpenseCalculator
{
    public function livingCostForMonth(string $month, array $costOfLiving): float
    {
        $bcol = (float) ($costOfLiving['bcol_amount'] ?? 0);
        $lite = (float) ($costOfLiving['fcol_lite_amount'] ?? 0);
        $max = (float) ($costOfLiving['fcol_max_amount'] ?? 0);
        $liteStart = $costOfLiving['fcol_lite_start_month'] ?? null;
        $maxStart = $costOfLiving['fcol_max_start_month'] ?? null;

        if ($maxStart && MonthHelper::isSameOrAfter($month, (string) $maxStart)) {
            return $max;
        }

        if ($liteStart && MonthHelper::isSameOrAfter($month, (string) $liteStart)) {
            return $lite;
        }

        return $bcol;
    }
}
