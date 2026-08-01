<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HistoryMonth;
use App\Models\PromptTemplate;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_templates_can_be_created_updated_and_deleted(): void
    {
        $create = $this->postJson(route('settings.prompt-templates.store'), [
            'name' => 'Custom review',
            'period_type' => 'custom',
            'body' => 'Review {{period}} with expenses of RM{{expense_total}}.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('template.name', 'Custom review')
            ->assertJsonPath('template.period_type', 'custom');
        $templateId = $create->json('template.id');

        $this->putJson(route('settings.prompt-templates.update', $templateId), [
            'name' => 'Updated review',
            'period_type' => 'weekly',
            'body' => 'Updated {{period}}',
        ])->assertOk()
            ->assertJsonPath('template.name', 'Updated review');

        $this->assertDatabaseHas('prompt_templates', [
            'id' => $templateId,
            'name' => 'Updated review',
        ]);

        $this->deleteJson(route('settings.prompt-templates.destroy', $templateId))
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

        $response = $this->postJson(route('settings.prompt-templates.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-26',
            'closing_coh' => 779.68,
            'closing_elr' => 635.42,
            'closing_epf' => 432.00,
            'additional_context' => 'I did not attend the event.',
            'questions' => 'How are things looking?',
        ]);

        $response->assertOk()
            ->assertJsonPath('period.label', '20/7–26/7')
            ->assertJsonPath('totals.expenses', 52.70)
            ->assertJsonPath('totals.incomes', 1576.60);
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('COH at RM779.68', $prompt);
        $this->assertStringContainsString('LFP at RM1415.10', $prompt);
        $this->assertStringContainsString('TFP at RM1847.10', $prompt);
        $this->assertStringContainsString('-RM52.70 from Food, of which', $prompt);
        $this->assertStringContainsString('-RM22.70 — Lunch', $prompt);
        $this->assertStringContainsString('+RM1576.60 from Salary — July salary', $prompt);
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

        $response = $this->postJson(route('settings.prompt-templates.compose'), [
            'template_id' => $template->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);

        $response->assertOk();
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('EOTM JULY 2026 POSITIONS, COMPARED WITH JUNE 2026:', $prompt);
        $this->assertStringContainsString('TFP RM1797.17 (up from RM941.85)', $prompt);
        $this->assertStringContainsString('LFP RM1365.17 (up from RM941.85)', $prompt);
        $this->assertStringContainsString('TOTAL EXPENSES AT RM0.00:', $prompt);
        $this->assertStringNotContainsString('{{', $prompt);
    }
}
