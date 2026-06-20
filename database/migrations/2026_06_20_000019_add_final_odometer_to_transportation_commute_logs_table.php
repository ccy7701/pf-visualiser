<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transportation_commute_logs', function (Blueprint $table): void {
            $table->decimal('final_odometer_km', 10, 2)->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('transportation_commute_logs', function (Blueprint $table): void {
            $table->dropColumn('final_odometer_km');
        });
    }
};
