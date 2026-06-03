<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\SalarySchedule;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\CounterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_counter_separates_actual_coh_from_expected_salary_accrual(): void
    {
        Setting::setValue('starting_amount', 871.61);

        SalarySchedule::query()->create([
            'effective_from' => '2026-06-01',
            'effective_until' => null,
            'monthly_net_salary' => 1766.35,
        ]);

        $service = app(CounterService::class);
        $endOfJune = CarbonImmutable::parse('2026-06-30 17:30:00', 'Asia/Kuala_Lumpur');

        $beforeSalaryReceipt = $service->snapshot($endOfJune);

        $this->assertSame(871.61, $beforeSalaryReceipt['actual_counter']);
        $this->assertSame(871.61, $beforeSalaryReceipt['counter']);
        $this->assertEqualsWithDelta(1766.35, $beforeSalaryReceipt['accrued_salary'], 0.01);
        $this->assertEqualsWithDelta(2637.96, $beforeSalaryReceipt['expected_counter'], 0.01);

        $salaryCategory = Category::query()->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);

        Transaction::query()->create([
            'type' => 'income',
            'datetime' => '2026-06-30 18:00:00',
            'category_id' => $salaryCategory->id,
            'amount' => 1766.35,
        ]);

        $afterSalaryReceipt = $service->snapshot($endOfJune);

        $this->assertSame(2637.96, $afterSalaryReceipt['actual_counter']);
        $this->assertSame(2637.96, $afterSalaryReceipt['counter']);
        $this->assertEqualsWithDelta(0.0, $afterSalaryReceipt['accrued_salary'], 0.01);
        $this->assertSame(2637.96, $afterSalaryReceipt['expected_counter']);
        $this->assertSame(0.0, $afterSalaryReceipt['increment_per_second']);

        $endOfJuly = CarbonImmutable::parse('2026-07-31 17:30:00', 'Asia/Kuala_Lumpur');
        $oneMonthAhead = $service->snapshot($endOfJuly);

        $this->assertSame(2637.96, $oneMonthAhead['actual_counter']);
        $this->assertEqualsWithDelta(1766.35, $oneMonthAhead['accrued_salary'], 0.01);
        $this->assertEqualsWithDelta(4404.31, $oneMonthAhead['expected_counter'], 0.01);
    }
}
