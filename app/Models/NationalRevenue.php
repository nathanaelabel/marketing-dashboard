<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NationalRevenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_name',
        'invoice_date',
        'total_revenue',
    ];

    protected $casts = [
        'invoice_date' => 'date',
    ];
}
