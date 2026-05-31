<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projection_actual_months', function (Blueprint $table): void {
            $table->json('expense_breakdown_json')->nullable()->after('closing_epf');
        });
    }

    public function down(): void
    {
        Schema::table('projection_actual_months', function (Blueprint $table): void {
            $table->dropColumn('expense_breakdown_json');
        });
    }
};
