<?php

namespace App\Services\Projection;

class ExpenseCalculator
{
    public function livingCostForMonth(string $month, array $costOfLiving): float
    {
        $budgets = $costOfLiving['budgets'] ?? [];
        $budgetSelections = $costOfLiving['monthly_budget_selection'] ?? [];
        $budgetKey = $this->budgetForMonth($month, $budgetSelections, $budgets);
        $selectedBudget = $budgets[$budgetKey] ?? ['category_allocations' => []];

        return array_reduce($selectedBudget['category_allocations'] ?? [], function (float $carry, array $allocation): float {
            return $carry + (float) ($allocation['amount'] ?? 0);
        }, 0.0);
    }

    private function budgetForMonth(string $month, array $budgetSelections, array $budgets): string
    {
        $defaultBudget = array_key_first($budgets) ?? 'bcol';

        foreach ($budgetSelections as $selection) {
            if (($selection['month'] ?? null) !== $month) {
                continue;
            }

            $budget = (string) ($selection['budget'] ?? $defaultBudget);
            if (array_key_exists($budget, $budgets)) {
                return $budget;
            }
        }

        return $defaultBudget;
    }
}
