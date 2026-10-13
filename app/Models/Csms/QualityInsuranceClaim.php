<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInsuranceClaim extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'quality_insurance_policy_id', 'quality_incident_id',
        'storage_lot_id', 'claim_reference', 'event_date', 'claim_amount', 'description',
        'evidence_path', 'status', 'insurer_response', 'outcome', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'claim_amount' => 'decimal:2',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(QualityInsurancePolicy::class, 'quality_insurance_policy_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(QualityIncident::class, 'quality_incident_id');
    }
}
