<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Transaction;
use App\Services\Counter\SalaryAccrualService;
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
        $actualCounter = $startingAmount + $netTransactions;

        $salary = $this->salaryAccrualService->computeAccruedSalary($asOf, $this->realizedSalaryByMonth());
        $expectedCounter = $actualCounter + $salary['accrued_salary'];
        $currentMonthIncome = (float) Transaction::query()
            ->where('type', 'income')
            ->whereBetween('datetime', [$asOf->copy()->startOfMonth(), $asOf->copy()->endOfMonth()])
            ->sum('amount');
        $currentMonthExpense = (float) Transaction::query()
            ->where('type', 'expense')
            ->whereBetween('datetime', [$asOf->copy()->startOfMonth(), $asOf->copy()->endOfMonth()])
            ->sum('amount');
        $currentMonthNetTransactions = $currentMonthIncome - $currentMonthExpense;
        $currentMonthUnpaidAccrual = (float) ($salary['current_month_accrued_salary'] ?? 0);
        $projectedEotmTfp = $startingAmount + $currentMonthNetTransactions + $currentMonthUnpaidAccrual;

        return [
            'as_of' => $asOf->toDateTimeString(),
            'starting_amount' => round($startingAmount, 2),
            'income_total' => round($income, 2),
            'expense_total' => round($expense, 2),
            'net_transactions' => round($netTransactions, 2),
            'accrued_salary' => round($salary['accrued_salary'], 2),
            'current_month_income_total' => round($currentMonthIncome, 2),
            'current_month_expense_total' => round($currentMonthExpense, 2),
            'current_month_net_transactions' => round($currentMonthNetTransactions, 2),
            'current_month_unpaid_accrual' => round($currentMonthUnpaidAccrual, 2),
            'projected_eotm_tfp' => round($projectedEotmTfp, 2),
            'scheduled_accrued_salary' => round($salary['scheduled_accrued_salary'], 2),
            'realized_salary' => round($salary['realized_salary'], 2),
            'actual_counter' => round($actualCounter, 2),
            'expected_counter' => round($expectedCounter, 2),
            'counter' => round($actualCounter, 2),
            'increment_per_second' => round((float) $salary['increment_per_second'], 10),
            'minute_rate' => round(((float) $salary['increment_per_second']) * 60, 8),
        ];
    }

    private function realizedSalaryByMonth(): array
    {
        return Transaction::query()
            ->where('type', 'income')
            ->whereHas('category', function ($query): void {
                $query->where('type', 'income')
                    ->where('name', 'Salary');
            })
            ->get(['datetime', 'amount'])
            ->groupBy(fn (Transaction $transaction): string => Carbon::parse($transaction->datetime)->format('Y-m'))
            ->map(fn ($transactions): float => (float) $transactions->sum('amount'))
            ->all();
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
