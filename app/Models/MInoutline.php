<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MInoutline extends Model
{
    use HasFactory;

    protected $table = 'm_inoutline';
    protected $primaryKey = 'm_inoutline_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'm_inoutline_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'm_inout_id',
        'c_orderline_id',
        'm_product_id',
        'movementqty',
        'line',
    ];

    protected $casts = [
        'movementqty' => 'decimal:2',
        'isactive' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function inout()
    {
        return $this->belongsTo(MInout::class, 'm_inout_id', 'm_inout_id');
    }

    public function orderLine()
    {
        return $this->belongsTo(COrderline::class, 'c_orderline_id', 'c_orderline_id');
    }

    public function product()
    {
        return $this->belongsTo(MProduct::class, 'm_product_id', 'm_product_id');
    }

    public function matchInvoices()
    {
        return $this->hasMany(MMatchinv::class, 'm_inoutline_id', 'm_inoutline_id');
    }

    public function invoiceLines()
    {
        return $this->hasMany(CInvoiceline::class, 'm_inoutline_id', 'm_inoutline_id');
    }
}
