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
        $this->assertSame(1766.35, $afterSalaryReceipt['current_month_net_transactions']);
        $this->assertEqualsWithDelta(0.0, $afterSalaryReceipt['current_month_unpaid_accrual'], 0.01);
        $this->assertSame(2637.96, $afterSalaryReceipt['projected_eotm_tfp']);
        $this->assertSame(2637.96, $afterSalaryReceipt['expected_counter']);
        $this->assertSame(0.0, $afterSalaryReceipt['increment_per_second']);

        $endOfJuly = CarbonImmutable::parse('2026-07-31 17:30:00', 'Asia/Kuala_Lumpur');
        $oneMonthAhead = $service->snapshot($endOfJuly);

        $this->assertSame(2637.96, $oneMonthAhead['actual_counter']);
        $this->assertEqualsWithDelta(1766.35, $oneMonthAhead['accrued_salary'], 0.01);
        $this->assertSame(0.0, $oneMonthAhead['current_month_net_transactions']);
        $this->assertEqualsWithDelta(1766.35, $oneMonthAhead['current_month_unpaid_accrual'], 0.01);
        $this->assertEqualsWithDelta(4404.31, $oneMonthAhead['projected_eotm_tfp'], 0.01);
        $this->assertEqualsWithDelta(4404.31, $oneMonthAhead['expected_counter'], 0.01);
    }

    public function test_salary_paid_in_following_month_does_not_clear_current_month_accrual(): void
    {
        Setting::setValue('starting_amount', 871.61);

        SalarySchedule::query()->create([
            'effective_from' => '2026-06-01',
            'effective_until' => null,
            'monthly_net_salary' => 1576.60,
        ]);

        $incomeCategory = Category::query()->create([
            'name' => 'Other Income',
            'type' => 'income',
        ]);
        $salaryCategory = Category::query()->create([
            'name' => 'Salary',
            'type' => 'income',
        ]);
        $expenseCategory = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        Transaction::query()->create([
            'type' => 'income',
            'datetime' => '2026-06-20 12:00:00',
            'category_id' => $incomeCategory->id,
            'amount' => 100.00,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-06-21 12:00:00',
            'category_id' => $expenseCategory->id,
            'amount' => 29.76,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-05 18:00:00',
            'category_id' => $expenseCategory->id,
            'amount' => 190.65,
        ]);
        Transaction::query()->create([
            'type' => 'income',
            'datetime' => '2026-07-05 18:30:00',
            'category_id' => $salaryCategory->id,
            'amount' => 1576.60,
        ]);

        $snapshot = app(CounterService::class)->snapshot(
            CarbonImmutable::parse('2026-07-05 23:59:00', 'Asia/Kuala_Lumpur')
        );

        $this->assertSame(941.85, $snapshot['current_month_starting_amount']);
        $this->assertSame(1385.95, $snapshot['current_month_net_transactions']);
        $this->assertSame(2327.80, $snapshot['actual_counter']);
        $this->assertEqualsWithDelta(205.64, $snapshot['current_month_unpaid_accrual'], 0.01);
        $this->assertEqualsWithDelta(2533.44, $snapshot['expected_counter'], 0.01);
        $this->assertEqualsWithDelta(2533.44, $snapshot['projected_eotm_tfp'], 0.01);
    }
}
