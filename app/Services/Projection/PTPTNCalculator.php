<?php

namespace App\Services\Projection;

class PTPTNCalculator
{
    public function repaymentForMonth(string $month, array $ptptn): float
    {
        if (! empty($ptptn['waiver_granted'])) {
            return 0.0;
        }

        $repaymentStartMonth = $ptptn['repayment_start_month'] ?? null;

        if (! $repaymentStartMonth) {
            return 0.0;
        }

        if (! MonthHelper::isSameOrAfter($month, (string) $repaymentStartMonth)) {
            return 0.0;
        }

        return (float) ($ptptn['monthly_repayment'] ?? 0);
    }
}
