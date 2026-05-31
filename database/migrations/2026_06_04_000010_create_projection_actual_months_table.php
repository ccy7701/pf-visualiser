<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projection_actual_months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scenario_id')->constrained('projection_scenarios')->cascadeOnDelete();
            $table->string('month', 7);
            $table->decimal('opening_coh', 12, 2)->nullable();
            $table->decimal('net_income', 12, 2)->nullable();
            $table->decimal('expenses', 12, 2)->nullable();
            $table->decimal('debt_servicing', 12, 2)->nullable();
            $table->decimal('closing_coh', 12, 2)->nullable();
            $table->decimal('closing_elr', 12, 2)->nullable();
            $table->decimal('closing_epf', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['scenario_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_actual_months');
    }
};
