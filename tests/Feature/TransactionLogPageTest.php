<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Subcategory;
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
        $response->assertSee('Values for this current month');
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

    public function test_transactions_can_store_edit_display_and_filter_optional_subcategories(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $lunch = Subcategory::query()->create(['category_id' => $food->id, 'name' => 'Lunch']);
        $dinner = Subcategory::query()->create(['category_id' => $food->id, 'name' => 'Dinner']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);

        $component = Livewire::test('transaction-log')
            ->set('type', 'expense')
            ->set('category_id', (string) $food->id)
            ->assertSee('No subcategory')
            ->assertSee('Lunch')
            ->assertSee('Dinner')
            ->set('subcategory_id', (string) $lunch->id)
            ->set('datetime', '15/07/2026 12:30')
            ->set('amount', '22.70')
            ->set('note', 'Workday meal')
            ->call('save')
            ->assertSet('errors', []);

        $transaction = Transaction::query()->where('note', 'Workday meal')->firstOrFail();
        $this->assertSame($food->id, $transaction->category_id);
        $this->assertSame($lunch->id, $transaction->subcategory_id);

        $component
            ->call('setRecentTransactionPeriod', 'custom')
            ->set('customPeriodStartDate', '2026-07-01')
            ->set('customPeriodEndDate', '2026-07-31')
            ->assertSee('Food')
            ->assertSee('Lunch')
            ->call('edit', $transaction->id)
            ->assertSet('subcategory_id', (string) $lunch->id)
            ->set('selectedCategoryIds', [(string) $food->id])
            ->set('selectedSubcategoryIds', [(string) $dinner->id])
            ->assertDontSee('Workday meal')
            ->set('selectedSubcategoryIds', [(string) $lunch->id])
            ->assertSee('Workday meal');

        Transaction::query()->create([
            'type' => 'income',
            'datetime' => '2026-07-15 17:00:00',
            'category_id' => $salary->id,
            'subcategory_id' => null,
            'amount' => 100,
            'note' => 'Category-only income',
        ]);

        Livewire::test('transaction-log')
            ->call('setRecentTransactionPeriod', 'custom')
            ->set('customPeriodStartDate', '2026-07-01')
            ->set('customPeriodEndDate', '2026-07-31')
            ->assertSee('Category-only income');

        $lunch->delete();
        $this->assertNull($transaction->fresh()->subcategory_id);
    }

    public function test_subcategory_must_belong_to_the_selected_category(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $transportation = Category::query()->create(['name' => 'Transportation', 'type' => 'expense']);
        $fuel = Subcategory::query()->create(['category_id' => $transportation->id, 'name' => 'Fuel']);

        Livewire::test('transaction-log')
            ->set('type', 'expense')
            ->set('category_id', (string) $food->id)
            ->set('subcategory_id', (string) $fuel->id)
            ->set('datetime', '15/07/2026 12:30')
            ->set('amount', '22.70')
            ->call('save')
            ->assertSet('errors.subcategory_id.0', 'Subcategory does not belong to the selected category.');

        $this->assertDatabaseCount('transactions', 0);
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
