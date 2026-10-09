<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringAlarm extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'chamber_id', 'sensor_id', 'sensor_reading_id',
        'type', 'severity', 'message', 'reading_temperature', 'reading_humidity', 'status',
        'acknowledged_by', 'acknowledged_at', 'resolved_by', 'resolved_at', 'resolution_comment',
        'escalated_by', 'escalated_at', 'escalation_level',
        'suppressed_until', 'suppression_reason', 'suppression_requested_by',
        'suppression_approved_by', 'suppression_status',
        'has_incident', 'incident_number', 'incident_notes', 'incident_created_by', 'incident_created_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'suppressed_until' => 'datetime',
            'incident_created_at' => 'datetime',
            'has_incident' => 'boolean',
        ];
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(MonitoringSensor::class, 'sensor_id');
    }

    public function reading(): BelongsTo
    {
        return $this->belongsTo(SensorReading::class, 'sensor_reading_id');
    }

    public function isSuppressed(): bool
    {
        return $this->status === 'suppressed'
            && $this->suppressed_until !== null
            && $this->suppressed_until->isFuture();
    }
}
