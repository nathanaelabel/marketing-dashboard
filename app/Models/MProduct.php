<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CInvoiceline;
use App\Models\COrderline;
use App\Models\MStorage;

class MProduct extends Model
{
    use HasFactory;

    protected $table = 'm_product';
    protected $primaryKey = 'm_product_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'm_product_id',
        'isactive',
        'name',
        'm_product_category_id',
        'm_productsubcat_id',
        'group1',
        'status',
    ];

    public function productCategory()
    {
        return $this->belongsTo(MProductCategory::class, 'm_product_category_id', 'm_product_category_id');
    }

    public function productSubcategory()
    {
        return $this->belongsTo(MProductsubcat::class, 'm_productsubcat_id', 'm_productsubcat_id');
    }

    public function invoiceLines()
    {
        return $this->hasMany(CInvoiceline::class, 'm_product_id', 'm_product_id');
    }

    public function orderLines()
    {
        return $this->hasMany(COrderline::class, 'm_product_id', 'm_product_id');
    }

    public function storages()
    {
        return $this->hasMany(MStorage::class, 'm_product_id', 'm_product_id');
    }
}
