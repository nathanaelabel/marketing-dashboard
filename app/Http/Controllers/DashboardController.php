<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        // The view will be created in a later step
        $currentDateFormatted = now()->format('d F Y');

        return view('dashboard', compact('startDate', 'endDate', 'currentDateFormatted'));
    }
}
