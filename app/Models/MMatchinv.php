<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MMatchinv extends Model
{
    use HasFactory;

    protected $table = 'm_matchinv';
    protected $primaryKey = 'm_matchinv_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'm_matchinv_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'c_invoiceline_id',
        'm_inoutline_id',
        'm_product_id',
        'qty',
        'datetrx',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'datetrx' => 'date',
        'isactive' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function invoiceLine()
    {
        return $this->belongsTo(CInvoiceline::class, 'c_invoiceline_id', 'c_invoiceline_id');
    }

    public function inoutLine()
    {
        return $this->belongsTo(MInoutline::class, 'm_inoutline_id', 'm_inoutline_id');
    }

    public function product()
    {
        return $this->belongsTo(MProduct::class, 'm_product_id', 'm_product_id');
    }
}
