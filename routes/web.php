<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NationalRevenueController;
use App\Http\Controllers\AccountsReceivableController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard routes
    Route::get('/dashboard', [NationalRevenueController::class, 'index'])->name('dashboard');
    Route::get('/national-revenue/data', [NationalRevenueController::class, 'getChartData'])->name('national-revenue.data');
    Route::get('/accounts-receivable', [AccountsReceivableController::class, 'index'])->name('accounts-receivable.index');
    Route::get('/accounts-receivable/data', [AccountsReceivableController::class, 'getARChartData'])->name('accounts-receivable.data');
});

require __DIR__ . '/auth.php';
