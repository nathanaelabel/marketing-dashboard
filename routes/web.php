<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesMetricsController;
use App\Http\Controllers\NationalRevenueController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\CategoryItemController;
use App\Http\Controllers\NationalYearlyController;
use App\Http\Controllers\MonthlyBranchController;
use App\Http\Controllers\BranchGrowthController;
use App\Http\Controllers\TargetRevenueController;
use App\Http\Controllers\BranchTargetController;
use App\Http\Controllers\SalesItemController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/report', [SalesItemController::class, 'index'])->name('report');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/national-revenue/data', [NationalRevenueController::class, 'data'])->name('national-revenue.data');
    Route::get('/accounts-receivable/data', [AccountsReceivableController::class, 'data'])->name('accounts-receivable.data');
    Route::get('/sales-metrics/data', [SalesMetricsController::class, 'getData'])->name('sales-metrics.data');
    Route::get('/sales-metrics/locations', [SalesMetricsController::class, 'getLocations'])->name('sales-metrics.locations');
    Route::get('/category-item/data', [CategoryItemController::class, 'getData'])->name('category-item.data');
    Route::get('/national-yearly/data', [NationalYearlyController::class, 'getData'])->name('national-yearly.data');
    Route::get('/monthly-branch/data', [MonthlyBranchController::class, 'getData'])->name('monthly-branch.data');
    Route::get('/monthly-branch/branches', [MonthlyBranchController::class, 'getBranches'])->name('monthly-branch.branches');
    Route::get('/branch-growth/data', [BranchGrowthController::class, 'getData'])->name('branch-growth.data');
    Route::get('/branch-growth/branches', [BranchGrowthController::class, 'getBranches'])->name('branch-growth.branches');
    Route::get('/target-revenue/data', [TargetRevenueController::class, 'getData'])->name('target-revenue.data');
    Route::get('/target-revenue/categories', [TargetRevenueController::class, 'getCategories'])->name('target-revenue.categories');
    Route::get('/branch-target/input', [BranchTargetController::class, 'showInputForm'])->name('branch-target.input');
    Route::post('/branch-target/save', [BranchTargetController::class, 'saveTargets'])->name('branch-target.save');
    Route::delete('/branch-target/delete', [BranchTargetController::class, 'deleteTargets'])->name('branch-target.delete');
    Route::get('/sales-item/data', [SalesItemController::class, 'getData'])->name('sales-item.data');
});

require __DIR__ . '/auth.php';
