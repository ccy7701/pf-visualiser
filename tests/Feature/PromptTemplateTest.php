<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HistoryMonth;
use App\Models\PromptTemplate;
use App\Models\Setting;
use App\Models\Subcategory;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_templates_can_be_created_updated_and_deleted(): void
    {
        $create = $this->postJson(route('prompt-studio.templates.store'), [
            'name' => 'Custom review',
            'period_type' => 'custom',
            'body' => 'Review {{period}} with expenses of RM{{expense_total}}.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('template.name', 'Custom review')
            ->assertJsonPath('template.period_type', 'custom');
        $templateId = $create->json('template.id');

        $this->putJson(route('prompt-studio.templates.update', $templateId), [
            'name' => 'Updated review',
            'period_type' => 'weekly',
            'body' => 'Updated {{period}}',
        ])->assertOk()
            ->assertJsonPath('template.name', 'Updated review');

        $this->assertDatabaseHas('prompt_templates', [
            'id' => $templateId,
            'name' => 'Updated review',
        ]);

        $this->deleteJson(route('prompt-studio.templates.destroy', $templateId))
            ->assertOk();
        $this->assertDatabaseMissing('prompt_templates', ['id' => $templateId]);
    }

    public function test_weekly_prompt_composes_transactions_positions_context_and_questions(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-21 12:00:00',
            'category_id' => $food->id,
            'note' => 'Lunch',
            'amount' => 22.70,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-22 19:00:00',
            'category_id' => $food->id,
            'note' => 'Dinner',
            'amount' => 30.00,
        ]);
        Transaction::query()->create([
            'type' => 'income',
            'datetime' => '2026-07-24 17:00:00',
            'category_id' => $salary->id,
            'note' => 'July salary',
            'amount' => 1576.60,
        ]);
        $template = PromptTemplate::query()->where('period_type', 'weekly')->firstOrFail();

        $response = $this->postJson(route('prompt-studio.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-26',
            'period_status' => 'ongoing',
            'closing_coh' => 779.68,
            'closing_elr' => 635.42,
            'closing_epf' => 432.00,
            'additional_context' => 'I did not attend the event.',
            'questions' => 'How are things looking?',
        ]);

        $response->assertOk()
            ->assertJsonPath('period.label', '20/7–26/7')
            ->assertJsonPath('period.status', 'ongoing')
            ->assertJsonPath('totals.expenses', 52.70)
            ->assertJsonPath('totals.incomes', 1576.60);
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('The week of 20/7–26/7 is still ongoing.', $prompt);
        $this->assertStringContainsString('COH at RM779.68', $prompt);
        $this->assertStringContainsString('LFP at RM1415.10', $prompt);
        $this->assertStringContainsString('TFP at RM1847.10', $prompt);
        $this->assertStringContainsString('-RM52.70 from Food', $prompt);
        $this->assertStringContainsString('+RM1576.60 from Salary', $prompt);
        $this->assertStringNotContainsString('Lunch', $prompt);
        $this->assertStringNotContainsString('Dinner', $prompt);
        $this->assertStringNotContainsString('July salary', $prompt);
        $this->assertStringContainsString('I did not attend the event.', $prompt);
        $this->assertStringContainsString('How are things looking?', $prompt);
    }

    public function test_monthly_prompt_compares_positions_with_the_previous_history_month(): void
    {
        HistoryMonth::query()->create([
            'month' => '2026-06',
            'closing_coh' => 496.37,
            'closing_elr' => 445.48,
            'closing_epf' => 0,
        ]);
        HistoryMonth::query()->create([
            'month' => '2026-07',
            'closing_coh' => 665.17,
            'closing_elr' => 700,
            'closing_epf' => 432,
        ]);
        $template = PromptTemplate::query()->where('period_type', 'monthly')->firstOrFail();

        $response = $this->postJson(route('prompt-studio.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'period_status' => 'complete',
        ]);

        $response->assertOk()
            ->assertJsonPath('period.status', 'complete');
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('The month of July 2026 is over.', $prompt);
        $this->assertStringContainsString('EOTM JULY 2026 POSITIONS, COMPARED WITH JUNE 2026:', $prompt);
        $this->assertStringContainsString('TFP RM1797.17 (up from RM941.85)', $prompt);
        $this->assertStringContainsString('LFP RM1365.17 (up from RM941.85)', $prompt);
        $this->assertStringContainsString('TOTAL EXPENSES AT RM0.00:', $prompt);
        $this->assertStringNotContainsString('{{', $prompt);
    }

    public function test_prompt_breakdowns_group_transactions_by_subcategory(): void
    {
        $transportation = Category::query()->create(['name' => 'Transportation', 'type' => 'expense']);
        $fuel = Subcategory::query()->create(['category_id' => $transportation->id, 'name' => 'Fuel']);
        $parking = Subcategory::query()->create(['category_id' => $transportation->id, 'name' => 'Parking']);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-21 12:00:00',
            'category_id' => $transportation->id,
            'subcategory_id' => $fuel->id,
            'note' => 'First fuel purchase',
            'amount' => 30.00,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-22 08:00:00',
            'category_id' => $transportation->id,
            'subcategory_id' => $fuel->id,
            'note' => 'Second fuel purchase',
            'amount' => 23.10,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-22 19:00:00',
            'category_id' => $transportation->id,
            'subcategory_id' => $parking->id,
            'note' => 'City parking',
            'amount' => 3.18,
        ]);
        $template = PromptTemplate::query()->where('period_type', 'weekly')->firstOrFail();

        $response = $this->postJson(route('prompt-studio.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-26',
            'period_status' => 'complete',
        ]);

        $response->assertOk();
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('-RM56.28 from Transportation, of which', $prompt);
        $this->assertStringContainsString("\t-RM53.10 from Fuel", $prompt);
        $this->assertStringContainsString("\t-RM3.18 from Parking", $prompt);
        $this->assertStringNotContainsString('First fuel purchase', $prompt);
        $this->assertStringNotContainsString('Second fuel purchase', $prompt);
        $this->assertStringNotContainsString('City parking', $prompt);
        $this->assertStringNotContainsString("\t\t", $prompt);
    }

    public function test_bnpl_expenses_are_combined_separately_from_the_expense_breakdown(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-21 12:00:00',
            'category_id' => $food->id,
            'note' => 'Ordinary lunch',
            'amount' => 25.00,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-22 19:00:00',
            'category_id' => $food->id,
            'note' => 'Instalment one',
            'amount' => 40.00,
            'is_bnpl' => true,
        ]);
        Transaction::query()->create([
            'type' => 'expense',
            'datetime' => '2026-07-23 19:00:00',
            'category_id' => $food->id,
            'note' => 'Instalment two',
            'amount' => 15.00,
            'is_bnpl' => true,
        ]);
        $template = PromptTemplate::query()->where('period_type', 'weekly')->firstOrFail();

        $response = $this->postJson(route('prompt-studio.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-26',
            'period_status' => 'complete',
        ]);

        $response->assertOk()
            ->assertJsonPath('totals.expenses', 80)
            ->assertJsonPath('totals.bnpl', 55);
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('-RM25.00 from Food', $prompt);
        $this->assertStringNotContainsString('-RM80.00 from Food', $prompt);
        $this->assertStringContainsString('-RM55.00 in BNPL payments (recorded separately)', $prompt);
        $this->assertStringNotContainsString('Instalment one', $prompt);
        $this->assertStringNotContainsString('Instalment two', $prompt);
    }

    public function test_current_week_positions_use_history_coh_instead_of_the_counter(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00');

        try {
            Setting::setValue('starting_amount', 9999.99);
            HistoryMonth::query()->create([
                'month' => '2026-08',
                'closing_coh' => 565.33,
                'closing_elr' => 700.11,
                'closing_epf' => 432,
            ]);
            $template = PromptTemplate::query()->where('period_type', 'weekly')->firstOrFail();

            $response = $this->postJson(route('prompt-studio.compose'), [
                'template_id' => $template->id,
                'start_date' => '2026-07-27',
                'end_date' => '2026-08-02',
                'period_status' => 'ongoing',
            ]);

            $response->assertOk();
            $prompt = $response->json('prompt');
            $this->assertStringContainsString('COH at RM565.33', $prompt);
            $this->assertStringContainsString('ELR at RM700.11', $prompt);
            $this->assertStringContainsString('EPF at RM432.00', $prompt);
            $this->assertStringContainsString('LFP at RM1265.44', $prompt);
            $this->assertStringContainsString('TFP at RM1697.44', $prompt);
            $this->assertStringNotContainsString('RM9999.99', $prompt);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_ongoing_monthly_report_does_not_insert_weekly_breakdown_wording(): void
    {
        $template = PromptTemplate::query()->where('period_type', 'monthly')->firstOrFail();

        $response = $this->postJson(route('prompt-studio.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'period_status' => 'ongoing',
        ]);

        $response->assertOk();
        $prompt = $response->json('prompt');
        $this->assertStringContainsString(
            'The month of August 2026 is still ongoing. I will give you the data first',
            $prompt,
        );
        $this->assertStringNotContainsString('Breakdown so far:', $prompt);
    }
}
