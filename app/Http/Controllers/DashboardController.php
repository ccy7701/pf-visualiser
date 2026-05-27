<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Services\CounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CounterService $counterService)
    {
    }

    public function index(): View
    {
        $snapshot = $this->counterService->snapshot();

        return view('dashboard', [
            'snapshot' => $snapshot,
            'categories' => Category::query()->orderBy('type')->orderBy('name')->get(),
            'transactions' => Transaction::query()
                ->with('category')
                ->latest('datetime')
                ->limit(20)
                ->get(),
        ]);
    }
    
    public function snapshot(): JsonResponse
    {
        return response()->json($this->counterService->snapshot());
    }
}
