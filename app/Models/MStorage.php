<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MStorage extends Model
{
    use HasFactory;

    protected $table = 'm_storage';
    public $incrementing = false;
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'm_product_id',
        'm_locator_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'qtyonhand',
        'm_attributesetinstance_id',
    ];

    /**
     * The primary key for the model.
     *
     * @var array
     */
    protected $primaryKey = ['m_product_id', 'm_locator_id', 'm_attributesetinstance_id'];

    public function product()
    {
        return $this->belongsTo(MProduct::class, 'm_product_id', 'm_product_id');
    }

    public function locator()
    {
        return $this->belongsTo(MLocator::class, 'm_locator_id', 'm_locator_id');
    }

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }
}
