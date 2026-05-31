<?php

namespace App\Services\Projection;

use Carbon\Carbon;

class ELRCalculator
{
    public function projectMonthBalance(string $month, float $openingElr, array $elr, array $events = []): array
    {
        $scheduleAmount = $this->allocationFromSchedules($month, $elr['schedules'] ?? []);

        if ($scheduleAmount !== null) {
            $monthlyContribution = $scheduleAmount;
        } else {
            $daily = (float) ($elr['daily_contribution'] ?? 0);
            $monthly = (float) ($elr['monthly_contribution'] ?? 0);
            $monthlyContribution = $monthly + ($daily * 30);
        }

        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'elr_override') {
                $monthlyContribution = (float) ($event['amount'] ?? 0);
            }
        }

        $monthlyContribution = max(0.0, $monthlyContribution);
        $compoundEnabled = (bool) ($elr['compound_interest_enabled'] ?? false);

        if (! $compoundEnabled) {
            return [
                'contribution' => $monthlyContribution,
                'interest' => 0.0,
                'closing_elr' => $openingElr + $monthlyContribution,
            ];
        }

        $annualRatePercent = max(0.0, (float) ($elr['annual_interest_rate_percent'] ?? 0));
        $dailyRate = ($annualRatePercent / 100) / 365;
        $daysInMonth = Carbon::createFromFormat('Y-m-d', $month.'-01')->daysInMonth;
        $dailyContribution = $daysInMonth > 0 ? ($monthlyContribution / $daysInMonth) : 0.0;

        $balance = $openingElr;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $interest = $balance * $dailyRate;
            $balance += $interest;
            $balance += $dailyContribution;
        }

        return [
            'contribution' => $monthlyContribution,
            'interest' => max(0.0, $balance - $openingElr - $monthlyContribution),
            'closing_elr' => $balance,
        ];
    }

    private function allocationFromSchedules(string $month, array $schedules): ?float
    {
        foreach ($schedules as $schedule) {
            $start = $schedule['start_month'] ?? null;
            $end = $schedule['end_month'] ?? null;

            if (! $start || ! $end) {
                continue;
            }

            $monthIndex = MonthHelper::toIndex($month);
            $startIndex = MonthHelper::toIndex((string) $start);
            $endIndex = MonthHelper::toIndex((string) $end);

            if ($monthIndex >= $startIndex && $monthIndex <= $endIndex) {
                $dailyAmount = (float) ($schedule['amount'] ?? 0);
                $daysInMonth = Carbon::createFromFormat('Y-m-d', $month.'-01')->daysInMonth;

                return $dailyAmount * $daysInMonth;
            }
        }

        return null;
    }
}
