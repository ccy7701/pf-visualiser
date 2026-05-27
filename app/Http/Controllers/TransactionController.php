<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'datetime' => ['required', 'date_format:d/m/Y H:i'],
            'category_id' => ['required', 'exists:categories,id'],
            'note' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $validated['datetime'] = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $validated['datetime'], 'Asia/Kuala_Lumpur');

        $category = Category::query()->findOrFail($validated['category_id']);

        if ($category->type && $category->type !== $validated['type']) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['category_id' => 'Category type does not match transaction type.'])
                ->withInput();
        }

        Transaction::query()->create($validated);

        return redirect()->route('dashboard')->with('status', 'Transaction recorded successfully.');
    }
}
