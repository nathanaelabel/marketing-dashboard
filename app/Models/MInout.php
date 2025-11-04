<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MInout extends Model
{
    use HasFactory;

    protected $table = 'm_inout';
    protected $primaryKey = 'm_inout_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'm_inout_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'issotrx',
        'documentno',
        'docstatus',
        'movementdate',
        'movementtype',
        'c_order_id',
        'c_invoice_id',
    ];

    protected $casts = [
        'movementdate' => 'date',
        'isactive' => 'boolean',
        'issotrx' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function order()
    {
        return $this->belongsTo(COrder::class, 'c_order_id', 'c_order_id');
    }

    public function invoice()
    {
        return $this->belongsTo(CInvoice::class, 'c_invoice_id', 'c_invoice_id');
    }

    public function inoutLines()
    {
        return $this->hasMany(MInoutline::class, 'm_inout_id', 'm_inout_id');
    }
}
