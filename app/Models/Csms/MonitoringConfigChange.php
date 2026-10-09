<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringConfigChange extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'chamber_id', 'kind',
        'alarm_temp_min', 'alarm_temp_max', 'alarm_humidity_min', 'alarm_humidity_max',
        'escalation_minutes', 'escalation_role', 'status', 'reason', 'created_by', 'approved_by',
    ];

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }
}
