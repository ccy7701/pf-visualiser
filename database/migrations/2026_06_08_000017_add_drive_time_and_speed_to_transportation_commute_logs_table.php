<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transportation_commute_logs', function (Blueprint $table): void {
            $table->dateTime('ended_at')->nullable()->after('driven_at');
            $table->decimal('average_speed_kmh', 10, 2)->nullable()->after('ended_at');
            $table->decimal('top_speed_kmh', 10, 2)->nullable()->after('average_speed_kmh');
            $table->unsignedInteger('drive_time_minutes')->nullable()->after('top_speed_kmh');

            $table->index('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('transportation_commute_logs', function (Blueprint $table): void {
            $table->dropIndex(['ended_at']);
            $table->dropColumn([
                'ended_at',
                'average_speed_kmh',
                'top_speed_kmh',
                'drive_time_minutes',
            ]);
        });
    }
};
