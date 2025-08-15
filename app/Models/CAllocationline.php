<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CAllocationline extends Model
{
    use HasFactory;

    protected $table = 'c_allocationline';
    protected $primaryKey = 'c_allocationline_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'c_allocationline_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'c_invoice_id',
        'amount',
        'discountamt',
        'writeoffamt',
        'overunderamt',
        'c_allocationhdr_id',
    ];

    public function invoice()
    {
        return $this->belongsTo(CInvoice::class, 'c_invoice_id', 'c_invoice_id');
    }

    public function allocationHeader()
    {
        return $this->belongsTo(CAllocationhdr::class, 'c_allocationhdr_id', 'c_allocationhdr_id');
    }

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }
}
