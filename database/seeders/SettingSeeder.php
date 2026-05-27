<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setValue('starting_amount', '800.00');
        Setting::setValue('simulation_now', '2026-05-29 18:00:00');
        Setting::setValue('use_simulation_now', '0');
    }
}
