<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MProduct;

class MProductsubcat extends Model
{
    use HasFactory;

    protected $table = 'm_productsubcat';
    protected $primaryKey = 'm_productsubcat_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'm_productsubcat_id',
        'ad_client_id',
        'name',
        'value',
    ];

    public function products()
    {
        return $this->hasMany(MProduct::class, 'm_productsubcat_id', 'm_productsubcat_id');
    }
}
