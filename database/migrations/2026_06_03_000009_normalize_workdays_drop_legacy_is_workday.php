<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workdays')
            ->where('status', 'absense')
            ->update(['status' => 'absence']);

        Schema::table('workdays', function (Blueprint $table) {
            if (Schema::hasColumn('workdays', 'is_workday')) {
                $table->dropColumn('is_workday');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            if (! Schema::hasColumn('workdays', 'is_workday')) {
                $table->boolean('is_workday')->default(true)->after('status');
            }
        });

        DB::table('workdays')
            ->where('status', 'workday')
            ->update(['is_workday' => true]);

        DB::table('workdays')
            ->whereIn('status', ['absence', 'holiday'])
            ->update(['is_workday' => false]);
    }
};
