<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

class TransportationLogController extends Controller
{
    public function index(): View
    {
        return view('transportation-log', [
            'theme' => Setting::getValue('theme', 'light'),
        ]);
    }
}
