<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MProduct;

class MProductCategory extends Model
{
    use HasFactory;

    protected $table = 'm_product_category';
    protected $primaryKey = 'm_product_category_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'm_product_category_id',
        'value',
        'name',
        'ad_client_id',
        'isactive',
    ];

    public function products()
    {
        return $this->hasMany(MProduct::class, 'm_product_category_id', 'm_product_category_id');
    }
}
