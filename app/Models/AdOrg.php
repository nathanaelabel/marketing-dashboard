<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CAllocationhdr;
use App\Models\CAllocationline;
use App\Models\CInvoice;
use App\Models\CInvoiceline;
use App\Models\COrder;
use App\Models\COrderline;
use App\Models\MProduct;
use App\Models\MLocator;

class AdOrg extends Model
{
    use HasFactory;

    protected $table = 'ad_org';
    protected $primaryKey = 'ad_org_id';
    public $incrementing = false;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'ad_org_id',
        'isactive',
        'name',
    ];

    public function allocationHeaders()
    {
        return $this->hasMany(CAllocationhdr::class, 'ad_org_id', 'ad_org_id');
    }

    public function allocationLines()
    {
        return $this->hasMany(CAllocationline::class, 'ad_org_id', 'ad_org_id');
    }

    public function invoices()
    {
        return $this->hasMany(CInvoice::class, 'ad_org_id', 'ad_org_id');
    }

    public function invoiceLines()
    {
        return $this->hasMany(CInvoiceline::class, 'ad_org_id', 'ad_org_id');
    }

    public function orders()
    {
        return $this->hasMany(COrder::class, 'ad_org_id', 'ad_org_id');
    }

    public function orderLines()
    {
        return $this->hasMany(COrderline::class, 'ad_org_id', 'ad_org_id');
    }

    public function locators()
    {
        return $this->hasMany(MLocator::class, 'ad_org_id', 'ad_org_id');
    }

    public function products()
    {
        return $this->hasMany(MProduct::class, 'ad_org_id', 'ad_org_id');
    }
}
