<?php

use App\Http\Controllers\CounterController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\TransportationLogController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VarianceAnalysisController;
use App\Http\Controllers\WorkdayController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/counter');
Route::get('/counter', [CounterController::class, 'index'])->name('counter');
Route::get('/transportation-log', [TransportationLogController::class, 'index'])->name('transportation-log.index');
Route::get('/transportation-log/snapshot', [TransportationLogController::class, 'snapshot'])->name('transportation-log.snapshot');
Route::post('/transportation-log/vehicles', [TransportationLogController::class, 'storeVehicle'])->name('transportation-log.vehicles.store');
Route::put('/transportation-log/vehicles/{vehicle}', [TransportationLogController::class, 'updateVehicle'])->name('transportation-log.vehicles.update');
Route::delete('/transportation-log/vehicles/{vehicle}', [TransportationLogController::class, 'destroyVehicle'])->name('transportation-log.vehicles.destroy');
Route::post('/transportation-log/fuel-logs', [TransportationLogController::class, 'storeFuelLog'])->name('transportation-log.fuel-logs.store');
Route::put('/transportation-log/fuel-logs/{fuelLog}', [TransportationLogController::class, 'updateFuelLog'])->name('transportation-log.fuel-logs.update');
Route::delete('/transportation-log/fuel-logs/{fuelLog}', [TransportationLogController::class, 'destroyFuelLog'])->name('transportation-log.fuel-logs.destroy');
Route::post('/transportation-log/commute-logs', [TransportationLogController::class, 'storeCommuteLog'])->name('transportation-log.commute-logs.store');
Route::put('/transportation-log/commute-logs/{commuteLog}', [TransportationLogController::class, 'updateCommuteLog'])->name('transportation-log.commute-logs.update');
Route::delete('/transportation-log/commute-logs/{commuteLog}', [TransportationLogController::class, 'destroyCommuteLog'])->name('transportation-log.commute-logs.destroy');
Route::redirect('/fuel-log', '/transportation-log');
Route::get('/projection', [ProjectionController::class, 'index'])->name('projection.index');
Route::post('/projection/run', [ProjectionController::class, 'run'])->name('projection.run');
Route::post('/projection/scenarios', [ProjectionController::class, 'saveScenario'])->name('projection.scenarios.save');
Route::get('/projection/scenarios/{scenario}', [ProjectionController::class, 'showScenario'])->name('projection.scenarios.show');
Route::delete('/projection/scenarios/{scenario}', [ProjectionController::class, 'destroyScenario'])->name('projection.scenarios.delete');
Route::post('/projection/compare', [ProjectionController::class, 'compare'])->name('projection.compare');
Route::get('/variance-analysis', [VarianceAnalysisController::class, 'index'])->name('variance-analysis.index');
Route::get('/variance-analysis/scenarios/{scenario}', [VarianceAnalysisController::class, 'showScenario'])->name('variance-analysis.scenarios.show');
Route::post('/variance-analysis/scenarios/{scenario}/actuals', [VarianceAnalysisController::class, 'saveActuals'])->name('variance-analysis.scenarios.actuals.save');

Route::get('/counter/snapshot', [CounterController::class, 'snapshot'])->name('counter.snapshot');
Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
Route::patch('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
Route::patch('/workdays/{workday}', [WorkdayController::class, 'update'])->name('workdays.update');
