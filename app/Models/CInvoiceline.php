<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CInvoiceline extends Model
{
    use HasFactory;

    protected $table = 'c_invoiceline';
    protected $primaryKey = 'c_invoiceline_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'c_invoiceline_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'c_invoice_id',
        'm_product_id',
        'm_inoutline_id',
        'qtyinvoiced',
        'priceactual',
        'linenetamt',
    ];

    public function invoice()
    {
        return $this->belongsTo(CInvoice::class, 'c_invoice_id', 'c_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(MProduct::class, 'm_product_id', 'm_product_id');
    }

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function inoutLine()
    {
        return $this->belongsTo(MInoutline::class, 'm_inoutline_id', 'm_inoutline_id');
    }

    public function matchInvoices()
    {
        return $this->hasMany(MMatchinv::class, 'c_invoiceline_id', 'c_invoiceline_id');
    }
}
