<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MPricelistVersion extends Model
{
    protected $table = 'm_pricelist_version';
    protected $primaryKey = 'm_pricelist_version_id';
    public $incrementing = false;

    protected $fillable = [
        'm_pricelist_version_id',
        'ad_org_id',
        'isactive',
        'createdby',
        'name',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }
}
