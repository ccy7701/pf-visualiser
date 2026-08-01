<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->enum('period_type', ['weekly', 'monthly', 'custom'])->default('weekly');
            $table->text('body');
            $table->timestamps();
        });

        $now = now();
        DB::table('prompt_templates')->insert([
            [
                'name' => 'Weekly financial review',
                'period_type' => 'weekly',
                'body' => <<<'TEMPLATE'
{{period_intro}}

CURRENT POSITIONS:
{{positions}}

EXPENSES TOTAL RM{{expense_total}}:
{{expense_breakdown}}

INCOMES THIS WEEK TOTAL RM{{income_total}}:
{{income_breakdown}}

{{additional_context}}

{{questions}}
TEMPLATE,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Month-end financial report',
                'period_type' => 'monthly',
                'body' => <<<'TEMPLATE'
{{period_intro}} I will give you the data first, then afterwards I will raise some questions. Only then do you give me your thorough responses.

{{positions_comparison}}

TOTAL EXPENSES AT RM{{expense_total}}:
{{expense_breakdown}}

TOTAL INCOMES AT RM{{income_total}}:
{{income_breakdown}}

{{additional_context}}

{{questions}}
TEMPLATE,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
