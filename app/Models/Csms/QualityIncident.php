<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityIncident extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'occurred_at', 'location', 'category', 'description',
        'severity', 'people_assets_lots', 'immediate_actions', 'investigation_notes',
        'status', 'evidence_path', 'reported_by', 'closure_requested_by', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(QualityCorrectiveAction::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
