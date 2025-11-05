<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Use yesterday (H-1) since dashboard is updated daily at night
        $yesterday = now()->subDay();
        $startDate = $yesterday->copy()->startOfMonth()->toDateString();
        $endDate = $yesterday->toDateString();

        $currentDateFormatted = $yesterday->format('d F Y');

        return view('dashboard', compact('startDate', 'endDate', 'currentDateFormatted'));
    }
}
