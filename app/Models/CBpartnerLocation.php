<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CBpartnerLocation extends Model
{
    use HasFactory;

    protected $table = 'c_bpartner_location';
    protected $primaryKey = 'c_bpartner_location_id';
    public $incrementing = false;
    protected $keyType = 'integer';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

    protected $fillable = [
        'c_bpartner_location_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'c_bpartner_id',
        'name',
        'isshipto',
        'isbillto',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function businessPartner()
    {
        return $this->belongsTo(CBpartner::class, 'c_bpartner_id', 'c_bpartner_id');
    }
}
