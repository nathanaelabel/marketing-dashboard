<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->toDateString();

        $currentDateFormatted = now()->format('d F Y');

        return view('dashboard', compact('startDate', 'endDate', 'currentDateFormatted'));
    }
}
