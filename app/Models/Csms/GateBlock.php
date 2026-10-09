<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class GateBlock extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'vehicle_no', 'driver_name', 'reason', 'created_by'];
}
