<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverdueAccountsReceivable extends Model
{
    use HasFactory;

    protected $table = 'overdue_accounts_receivables';

    protected $fillable = [
        'branch_name',
        'calculation_date',
        'days_1_30_overdue_amount',
        'days_31_60_overdue_amount',
        'days_61_90_overdue_amount',
        'days_over_90_overdue_amount',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'days_1_30_overdue_amount' => 'float',
        'days_31_60_overdue_amount' => 'float',
        'days_61_90_overdue_amount' => 'float',
        'days_over_90_overdue_amount' => 'float',
    ];
}
