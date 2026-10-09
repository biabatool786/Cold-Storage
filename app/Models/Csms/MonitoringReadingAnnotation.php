<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringReadingAnnotation extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'sensor_reading_id', 'note', 'created_by'];

    public function reading(): BelongsTo
    {
        return $this->belongsTo(SensorReading::class, 'sensor_reading_id');
    }
}
