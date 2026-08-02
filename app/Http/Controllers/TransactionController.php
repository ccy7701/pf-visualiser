<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use Carbon\Carbon;
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
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'note' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $validated['datetime'] = Carbon::createFromFormat('d/m/Y H:i', $validated['datetime'], 'Asia/Kuala_Lumpur');
        $validated['subcategory_id'] = $validated['subcategory_id'] ?? null;

        $category = Category::query()->findOrFail($validated['category_id']);

        if ($category->type && $category->type !== $validated['type']) {
            return redirect()
                ->route('counter')
                ->withErrors(['category_id' => 'Category type does not match transaction type.'])
                ->withInput();
        }

        if (! $this->subcategoryBelongsToCategory($validated['subcategory_id'] ?? null, $category)) {
            return redirect()
                ->route('counter')
                ->withErrors(['subcategory_id' => 'Subcategory does not belong to the selected category.'])
                ->withInput();
        }

        Transaction::query()->create($validated);

        return redirect()->route('counter')->with('status', 'Transaction recorded successfully.');
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'datetime' => ['required', 'date_format:d/m/Y H:i'],
            'category_id' => ['required', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'note' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $validated['datetime'] = Carbon::createFromFormat('d/m/Y H:i', $validated['datetime'], 'Asia/Kuala_Lumpur');
        $validated['subcategory_id'] = $validated['subcategory_id'] ?? null;

        $category = Category::query()->findOrFail($validated['category_id']);

        if ($category->type && $category->type !== $validated['type']) {
            return redirect()
                ->route('counter')
                ->withErrors(['category_id' => 'Category type does not match transaction type.'])
                ->withInput();
        }

        if (! $this->subcategoryBelongsToCategory($validated['subcategory_id'] ?? null, $category)) {
            return redirect()
                ->route('counter')
                ->withErrors(['subcategory_id' => 'Subcategory does not belong to the selected category.'])
                ->withInput();
        }

        $transaction->update($validated);

        return redirect()->route('counter')->with('status', 'Transaction updated successfully.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('counter')->with('status', 'Transaction deleted successfully.');
    }

    private function subcategoryBelongsToCategory(int|string|null $subcategoryId, Category $category): bool
    {
        if ($subcategoryId === null || $subcategoryId === '') {
            return true;
        }

        return Subcategory::query()
            ->whereKey($subcategoryId)
            ->where('category_id', $category->id)
            ->exists();
    }
}
