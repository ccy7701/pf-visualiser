<?php

namespace Tests\Unit;

use App\Services\Projection\PTPTNCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PTPTNCalculatorTest extends TestCase
{
    #[DataProvider('waiverRepaymentProvider')]
    public function test_waiver_takes_effect_after_interim_payment_months(string $month, float $expected): void
    {
        $calculator = new PTPTNCalculator();
        $ptptn = [
            'waiver_granted' => true,
            'monthly_repayment' => 120,
            'repayment_start_month' => '2026-08',
            'interim_payment_months' => 2,
        ];

        $this->assertSame($expected, $calculator->repaymentForMonth($month, $ptptn));
    }

    public static function waiverRepaymentProvider(): array
    {
        return [
            'before repayment starts' => ['2026-07', 0.0],
            'first interim month' => ['2026-08', 120.0],
            'second interim month' => ['2026-09', 120.0],
            'waiver effective' => ['2026-10', 0.0],
        ];
    }

    public function test_non_waived_repayment_continues_after_start_month(): void
    {
        $calculator = new PTPTNCalculator();
        $ptptn = [
            'waiver_granted' => false,
            'monthly_repayment' => 120,
            'repayment_start_month' => '2026-08',
            'interim_payment_months' => null,
        ];

        $this->assertSame(120.0, $calculator->repaymentForMonth('2027-08', $ptptn));
    }

    public function test_legacy_waived_payload_defaults_to_one_interim_payment_month(): void
    {
        $calculator = new PTPTNCalculator();
        $ptptn = [
            'waiver_granted' => true,
            'monthly_repayment' => 120,
            'repayment_start_month' => '2026-08',
        ];

        $this->assertSame(120.0, $calculator->repaymentForMonth('2026-08', $ptptn));
        $this->assertSame(0.0, $calculator->repaymentForMonth('2026-09', $ptptn));
    }
}
