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
            'status' => ['required', 'in:workday,absence,holiday'],
        ]);

        $workday->update([
            'status' => $validated['status'],
        ]);

        return redirect()->route('dashboard');
    }
}
