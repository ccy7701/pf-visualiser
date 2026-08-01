<?php

namespace App\Http\Controllers;

use App\Models\PromptTemplate;
use App\Models\Setting;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings', [
            'theme' => Setting::getValue('theme', 'light'),
            'promptTemplates' => PromptTemplate::query()
                ->orderBy('id')
                ->get(['id', 'name', 'period_type', 'body']),
        ]);
    }
}
