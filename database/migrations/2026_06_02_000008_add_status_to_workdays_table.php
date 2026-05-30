<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            if (! Schema::hasColumn('workdays', 'status')) {
                $table->string('status', 16)->default('workday')->after('date');
            }
        });

        if (Schema::hasColumn('workdays', 'is_workday')) {
            DB::table('workdays')
                ->where('is_workday', true)
                ->update(['status' => 'workday']);

            DB::table('workdays')
                ->where('is_workday', false)
                ->update(['status' => 'holiday']);
        }
    }

    public function down(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            if (Schema::hasColumn('workdays', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
