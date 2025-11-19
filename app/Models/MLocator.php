<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MStorage;
use App\Models\MInoutline;

class MLocator extends Model
{
    use HasFactory;

    protected $table = 'm_locator';
    protected $primaryKey = 'm_locator_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'm_locator_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'value',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }

    public function storages()
    {
        return $this->hasMany(MStorage::class, 'm_locator_id', 'm_locator_id');
    }

    public function mInoutlines()
    {
        return $this->hasMany(MInoutline::class, 'm_locator_id', 'm_locator_id');
    }
}
