<?php

namespace App\Services\Projection;

class PTPTNCalculator
{
    public function repaymentForMonth(string $month, array $ptptn): float
    {
        $repaymentStartMonth = $ptptn['repayment_start_month'] ?? null;

        if (! $repaymentStartMonth) {
            return 0.0;
        }

        if (! MonthHelper::isSameOrAfter($month, (string) $repaymentStartMonth)) {
            return 0.0;
        }

        if (! empty($ptptn['waiver_granted'])) {
            $interimPaymentMonths = max(1, (int) ($ptptn['interim_payment_months'] ?? 1));
            $monthsSinceRepaymentStarted = MonthHelper::toIndex($month) - MonthHelper::toIndex((string) $repaymentStartMonth);

            if ($monthsSinceRepaymentStarted >= $interimPaymentMonths) {
                return 0.0;
            }
        }

        return (float) ($ptptn['monthly_repayment'] ?? 0);
    }
}
