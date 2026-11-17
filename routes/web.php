<?php

use App\Http\Controllers\Generations\GenerationController;
use App\Http\Controllers\Generations\GenerationEstimateController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');

    Route::post('generations/estimate', GenerationEstimateController::class)
        ->middleware('throttle:60,1')
        ->name('generations.estimate');

    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('generations.store');
});

require __DIR__ . '/settings.php';
