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
        return view('report');
    }
}
