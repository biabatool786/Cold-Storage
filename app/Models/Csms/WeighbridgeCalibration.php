<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class WeighbridgeCalibration extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'weighbridge_name', 'calibrated_on', 'next_due_on',
        'result', 'certificate_ref', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['calibrated_on' => 'date', 'next_due_on' => 'date'];
    }
}
