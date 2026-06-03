<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_months', function (Blueprint $table): void {
            $table->decimal('closing_elr', 12, 2)->nullable()->after('closing_coh');
            $table->decimal('closing_epf', 12, 2)->nullable()->after('closing_elr');
        });
    }

    public function down(): void
    {
        Schema::table('history_months', function (Blueprint $table): void {
            $table->dropColumn(['closing_elr', 'closing_epf']);
        });
    }
};
