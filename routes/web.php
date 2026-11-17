<?php

use App\Http\Controllers\Admin\SpendReportController;
use App\Http\Controllers\Credits\CreditPurchaseController;
use App\Http\Controllers\Generations\GenerationController;
use App\Http\Controllers\Generations\GenerationEstimateController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generations')->name('home');      // guests are sent on to the login page

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/generations')->name('dashboard');       // the studio is the home page

    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');

    Route::post('generations/estimate', GenerationEstimateController::class)
        ->middleware('throttle:60,1')
        ->name('generations.estimate');

    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('generations.store');

    Route::post('credits/purchases', CreditPurchaseController::class)
        ->middleware('throttle:10,1')
        ->name('credits.purchase');

    Route::get('admin/report', SpendReportController::class)
        ->middleware('can:viewAdminReport')
        ->name('admin.report');
});

require __DIR__ . '/settings.php';
