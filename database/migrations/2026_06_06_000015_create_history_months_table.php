<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history_months', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->decimal('closing_coh', 12, 2);
            $table->json('expense_breakdown_json')->nullable();
            $table->json('income_breakdown_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_months');
    }
};
