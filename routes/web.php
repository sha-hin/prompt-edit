<?php

use App\Http\Controllers\Admin\SpendReportController;
use App\Http\Controllers\Admin\UserReportController;
use App\Http\Controllers\Credits\CreditPurchaseController;
use App\Http\Controllers\Generations\GenerationController;
use App\Http\Controllers\Generations\GenerationEstimateController;
use App\Http\Controllers\Generations\GenerationImageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generations')->name('home');      // guests are sent on to the login page

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::redirect('dashboard', '/generations')->name('dashboard');       // the studio is the home page

    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('generations/history', [GenerationController::class, 'history'])->name('generations.history');
    Route::get('generations/{generation}', [GenerationController::class, 'show'])->name('generations.show');
    Route::get('generations/{generation}/download', GenerationImageController::class)
        ->middleware('throttle:downloads')
        ->name('generations.download');

    Route::post('generations/estimate', GenerationEstimateController::class)
        ->middleware('throttle:estimates')
        ->name('generations.estimate');

    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');

    Route::post('credits/purchases', CreditPurchaseController::class)
        ->middleware('throttle:credit-purchases')
        ->name('credits.purchase');

    Route::middleware('can:viewAdminReport')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('report', SpendReportController::class)->name('report');
        Route::get('users', [UserReportController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserReportController::class, 'show'])->name('users.show');
    });
});

require __DIR__ . '/settings.php';
