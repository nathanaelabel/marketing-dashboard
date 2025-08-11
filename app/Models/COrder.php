<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\COrderline;

class COrder extends Model
{
    use HasFactory;

    protected $table = 'c_order';
    protected $primaryKey = 'c_order_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'c_order_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'issotrx',
        'documentno',
        'docstatus',
        'dateordered',
        'grandtotal',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function orderLines()
    {
        return $this->hasMany(COrderline::class, 'c_order_id', 'c_order_id');
    }
}
