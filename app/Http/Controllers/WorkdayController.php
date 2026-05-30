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
            'status' => ['required', 'in:workday,absence,holiday,absense'],
        ]);

        $status = $validated['status'] === 'absense' ? 'absence' : $validated['status'];

        $workday->update([
            'status' => $status,
            'is_workday' => $status === 'workday',
        ]);

        return redirect()->route('dashboard');
    }
}
