<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $response->assertSee('Period');
        $response->assertSee('Filter transactions');
    }

    public function test_transactions_can_be_filtered_by_note_and_category_within_the_selected_period(): void
    {
        $category = Category::query()->create([
            'name' => 'Food',
            'type' => 'expense',
        ]);

        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => now('Asia/Kuala_Lumpur'),
            'category_id' => $category->id,
            'amount' => 12.50,
            'note' => 'Morning coffee with Sam',
        ]);

        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => now('Asia/Kuala_Lumpur'),
            'category_id' => $category->id,
            'amount' => 25,
            'note' => 'Lunch with Alex',
        ]);

        $incomeCategory = Category::query()->create([
            'name' => 'Other Income',
            'type' => 'income',
        ]);

        Transaction::query()->create([
            'type' => 'income',
            'datetime' => now('Asia/Kuala_Lumpur'),
            'category_id' => $incomeCategory->id,
            'amount' => 100,
            'note' => 'Weekend sale',
        ]);

        Livewire::test('transaction-log')
            ->assertSet('periodIncomeTotal', 100.0)
            ->assertSet('periodExpenseTotal', 37.5)
            ->assertSet('showNoteSearch', false)
            ->call('toggleNoteSearch')
            ->assertSet('showNoteSearch', true)
            ->assertSee('Search transaction notes')
            ->assertSee('Income categories')
            ->assertSee('Expense categories')
            ->set('noteSearch', 'COFFEE')
            ->assertSee('Morning coffee with Sam')
            ->assertDontSee('Lunch with Alex')
            ->assertSet('periodIncomeTotal', 0.0)
            ->assertSet('periodExpenseTotal', 12.5)
            ->call('closeNoteSearch')
            ->assertSet('showNoteSearch', false)
            ->assertSet('noteSearch', 'COFFEE')
            ->assertDontSee('Lunch with Alex')
            ->call('resetTransactionFilters')
            ->assertSet('noteSearch', '')
            ->assertSee('Lunch with Alex')
            ->set('selectedCategoryIds', [(string) $incomeCategory->id])
            ->assertSee('Weekend sale')
            ->assertDontSee('Morning coffee with Sam')
            ->assertDontSee('Lunch with Alex')
            ->assertSet('periodIncomeTotal', 100.0)
            ->assertSet('periodExpenseTotal', 0.0);
    }

    public function test_transactions_can_be_filtered_by_a_custom_period(): void
    {
        $category = Category::query()->create([
            'name' => 'Shopping',
            'type' => 'expense',
        ]);

        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-01-10 12:00:00',
            'category_id' => $category->id,
            'amount' => 10,
            'note' => 'January purchase',
        ]);

        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-02-10 12:00:00',
            'category_id' => $category->id,
            'amount' => 20,
            'note' => 'February purchase',
        ]);

        Livewire::test('transaction-log')
            ->call('setRecentTransactionPeriod', 'custom')
            ->set('customPeriodStartDate', '2026-01-01')
            ->set('customPeriodEndDate', '2026-01-31')
            ->assertSee('January purchase')
            ->assertDontSee('February purchase')
            ->assertSet('periodIncomeTotal', 0.0)
            ->assertSet('periodExpenseTotal', 10.0);
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
