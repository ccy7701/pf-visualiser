<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WorkdayController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/counter/snapshot', [DashboardController::class, 'snapshot'])->name('counter.snapshot');
Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
Route::patch('/workdays/{workday}', [WorkdayController::class, 'update'])->name('workdays.update');
