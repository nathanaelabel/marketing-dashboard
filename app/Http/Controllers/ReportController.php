<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display the unified report page with sales item and sales family tables.
     */
    public function index()
    {
        // Gunakan H-1 karena dashboard diupdate setiap malam
        $yesterday = now()->subDay();
        $startDate = $yesterday->copy()->startOfMonth()->toDateString();
        $endDate = $yesterday->toDateString();

        $currentDateFormatted = $yesterday->format('d F Y');

        return view('report', compact('startDate', 'endDate', 'currentDateFormatted'));
    }
}
