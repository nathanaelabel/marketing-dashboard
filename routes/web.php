<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NationalRevenueController;
use App\Http\Controllers\AccountsReceivableController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/national-revenue/data', [NationalRevenueController::class, 'data'])->name('national-revenue.data');
    Route::get('/accounts-receivable/data', [AccountsReceivableController::class, 'data'])->name('accounts-receivable.data');
});

require __DIR__ . '/auth.php';
