<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityCorrectiveAction extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'quality_ncr_id', 'quality_incident_id', 'description',
        'responsible_user_id', 'responsible_team', 'due_date', 'priority', 'status',
        'completion_evidence', 'assigned_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(QualityNcr::class, 'quality_ncr_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(QualityIncident::class, 'quality_incident_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->status !== 'completed'
            && $this->due_date->lt(now()->startOfDay());
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
