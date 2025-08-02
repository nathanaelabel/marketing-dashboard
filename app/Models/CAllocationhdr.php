<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CAllocationhdr extends Model
{
    use HasFactory;

    protected $table = 'c_allocationhdr';
    protected $primaryKey = 'c_allocationhdr_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'c_allocationhdr_id',
        'ad_client_id',
        'ad_org_id',
        'isactive',
        'documentno',
        'datetrx',
        'approvalamt',
        'docstatus',
    ];

    public function organization()
    {
        return $this->belongsTo(AdOrg::class, 'ad_org_id', 'ad_org_id');
    }
}
