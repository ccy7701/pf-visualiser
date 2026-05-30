<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CounterService
{
    public function __construct(private readonly SalaryAccrualService $salaryAccrualService)
    {
    }

    public function snapshot(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf) : $this->resolveAsOf();

        $startingAmount = (float) Setting::getValue('starting_amount', 0);
        $income = (float) Transaction::query()->where('type', 'income')->sum('amount');
        $expense = (float) Transaction::query()->where('type', 'expense')->sum('amount');
        $netTransactions = $income - $expense;

        $salary = $this->salaryAccrualService->computeAccruedSalary($asOf);
        $counter = $startingAmount + $netTransactions + $salary['accrued_salary'];

        return [
            'as_of' => $asOf->toDateTimeString(),
            'starting_amount' => round($startingAmount, 2),
            'income_total' => round($income, 2),
            'expense_total' => round($expense, 2),
            'net_transactions' => round($netTransactions, 2),
            'accrued_salary' => round($salary['accrued_salary'], 2),
            'counter' => round($counter, 2),
            'increment_per_second' => round((float) $salary['increment_per_second'], 10),
            'minute_rate' => round(((float) $salary['increment_per_second']) * 60, 8),
        ];
    }

    private function resolveAsOf(): Carbon
    {
        $simulationNow = Setting::getValue('simulation_now');
        $useSimulationNow = filter_var(Setting::getValue('use_simulation_now', false), FILTER_VALIDATE_BOOL);

        if ($useSimulationNow && $simulationNow) {
            return Carbon::parse($simulationNow, 'Asia/Kuala_Lumpur');
        }

        return now('Asia/Kuala_Lumpur');
    }
}
