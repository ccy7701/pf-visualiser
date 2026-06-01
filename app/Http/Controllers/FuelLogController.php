<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

class FuelLogController extends Controller
{
    public function index(): View
    {
        return view('fuel-log', [
            'theme' => Setting::getValue('theme', 'light'),
        ]);
    }
}
