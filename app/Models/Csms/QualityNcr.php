<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityNcr extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'reference_number', 'quality_inspection_id', 'storage_lot_id',
        'description', 'severity', 'affected_quantity', 'status', 'reported_by',
        'closure_requested_by', 'closed_by', 'closed_at', 'closure_evidence',
    ];

    protected function casts(): array
    {
        return [
            'affected_quantity' => 'decimal:3',
            'closed_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class, 'quality_inspection_id');
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
