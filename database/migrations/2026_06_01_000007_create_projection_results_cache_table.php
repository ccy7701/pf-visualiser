<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projection_results_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('projection_scenarios')->cascadeOnDelete();
            $table->json('results_json');
            $table->timestamps();

            $table->unique('scenario_id');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_results_cache');
    }
};
