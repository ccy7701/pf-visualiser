<?php

namespace Database\Seeders;

use App\Models\SalarySchedule;
use Illuminate\Database\Seeder;

class SalaryScheduleSeeder extends Seeder
{
    public function run(): void
    {
        SalarySchedule::query()->updateOrCreate(
            ['effective_from' => '2026-06-01'],
            [
                'effective_until' => '2026-09-30',
                'monthly_net_salary' => 1751.70,
                'notes' => 'Initial net salary period',
            ]
        );

        SalarySchedule::query()->updateOrCreate(
            ['effective_from' => '2026-10-01'],
            [
                'effective_until' => null,
                'monthly_net_salary' => 2101.90,
                'notes' => 'Updated net salary period',
            ]
        );
    }
}
