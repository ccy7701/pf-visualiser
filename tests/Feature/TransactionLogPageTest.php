<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_log_has_dedicated_page(): void
    {
        $response = $this->get(route('transaction-log.index'));

        $response->assertOk();
        $response->assertSee('Transaction Log');
        $response->assertSee('Log New Transaction');
        $response->assertSee('Values for this month');
        $response->assertSee('Starting Amount');
        $response->assertSee('Net Transactions');
        $response->assertSee('Unpaid Accrual');
        $response->assertSee('Projected EOTM TFP');
        $response->assertSee('Daily');
        $response->assertSee('Weekly');
        $response->assertSee('Monthly');
        $response->assertSee('Annually');
    }

    public function test_counter_no_longer_contains_the_settings_popup(): void
    {
        $response = $this->get(route('counter'));

        $response->assertOk();
        $response->assertDontSee('id="fabBtn"', false);
        $response->assertDontSee('Workday Calendar');
        $response->assertDontSee('Salary Schedules');
        $response->assertDontSee('id="counterNotificationToggle"', false);
    }
}
