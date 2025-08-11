<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class COrderline extends Model
{
    use HasFactory;

    protected $table = 'c_orderline';
    protected $primaryKey = 'c_orderline_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'c_orderline_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'c_order_id',
        'm_product_id',
        'qtyordered',
        'qtydelivered',
        'qtyinvoiced',
        'priceactual',
    ];

    public function order()
    {
        return $this->belongsTo(COrder::class, 'c_order_id', 'c_order_id');
    }

    public function product()
    {
        return $this->belongsTo(MProduct::class, 'm_product_id', 'm_product_id');
    }

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }
}
