<?php

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MProductprice extends Model
{
    use HasCompositePrimaryKey;
    use HasFactory;

    protected $table = 'm_productprice';
    protected $primaryKey = ['m_pricelist_version_id', 'm_product_id'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'm_pricelist_version_id',
        'm_product_id',
        'ad_org_id',
        'isactive',
        'pricelist',
        'pricestd',
        'pricelimit',
        'created',
        'updated',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }
}
