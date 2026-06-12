<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\CounterService;
use Illuminate\View\View;

class TransactionLogPageController extends Controller
{
    public function __construct(private readonly CounterService $counterService)
    {
    }

    public function index(): View
    {
        return view('transaction-log', [
            'snapshot' => $this->counterService->snapshot(),
            'theme' => Setting::getValue('theme', 'light'),
        ]);
    }
}
