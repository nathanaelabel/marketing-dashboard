<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CInvoiceline;
use App\Models\CAllocationline;

class CInvoice extends Model
{
    use HasFactory;

    protected $table = 'c_invoice';
    protected $primaryKey = 'c_invoice_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'c_invoice_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'issotrx',
        'documentno',
        'docstatus',
        'dateinvoiced',
        'grandtotal',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function invoiceLines()
    {
        return $this->hasMany(CInvoiceline::class, 'c_invoice_id', 'c_invoice_id');
    }

    public function allocationLines()
    {
        return $this->hasMany(CAllocationline::class, 'c_invoice_id', 'c_invoice_id');
    }
}
