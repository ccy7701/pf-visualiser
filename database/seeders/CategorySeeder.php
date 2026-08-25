<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $expenseCategories = [
            'Family',
            'Groceries',
            'Food',
            'Household',
            'Health',
            'Personal Care',
            'IT Product',
            'Prepaid Reload',
            'Transportation',
            'Apparel',
            'Books and Stationery',
            'Fees',
            'Subscriptions',
            'Entertainment',
            'Gifts and Giving',
            'Travel',
            'Payments',
            'Special Projects',
            'Others',
        ];

        $incomeCategories = [
            'Allowance',
            'PTPTN',
            'Salary',
            'Petty Cash',
            'Bonus',
            'Loans',
            'Payments',
            'Reimbursement',
            'Deposit',
            'Money Pot Share',
            'Cash Assistance',
            'Interest',
            'EPF',
            'Fees',
            'Snacktime',
            'Others',
        ];

        foreach ($expenseCategories as $name) {
            Category::query()->firstOrCreate([
                'name' => $name,
                'type' => 'expense',
            ]);
        }

        foreach ($incomeCategories as $name) {
            Category::query()->firstOrCreate([
                'name' => $name,
                'type' => 'income',
            ]);
        }
    }
}
