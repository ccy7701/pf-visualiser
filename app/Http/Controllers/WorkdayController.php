<?php

namespace App\Http\Controllers;

use App\Models\Workday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkdayController extends Controller
{
    public function update(Request $request, Workday $workday): RedirectResponse
    {
        $validated = $request->validate([
            'is_workday' => ['required', 'boolean'],
        ]);

        $workday->update($validated);

        return redirect()->route('dashboard');
    }
}
