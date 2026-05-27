<?php

namespace Database\Seeders;

use App\Models\Workday;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WorkdaySeeder extends Seeder
{
    public function run(): void
    {
        $start = Carbon::parse('2026-05-01');
        $end = Carbon::parse('2026-12-31');

        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            Workday::query()->updateOrCreate(
                ['date' => $cursor->toDateString()],
                ['is_workday' => $cursor->isWeekday()]
            );

            $cursor->addDay();
        }
    }
}
