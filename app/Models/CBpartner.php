<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CBpartner extends Model
{
    use HasFactory;

    protected $table = 'c_bpartner';
    protected $primaryKey = 'c_bpartner_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'c_bpartner_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'value',
        'name',
        'iscustomer',
        'isvendor',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function locations()
    {
        return $this->hasMany(CBpartnerLocation::class, 'c_bpartner_id', 'c_bpartner_id');
    }

    public function invoices()
    {
        return $this->hasMany(CInvoice::class, 'c_bpartner_id', 'c_bpartner_id');
    }
}
