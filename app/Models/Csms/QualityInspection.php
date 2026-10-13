<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityInspection extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'category', 'quality_checklist_template_id',
        'quality_checklist_version_id', 'storage_lot_id', 'goods_receipt_id',
        'chamber_id', 'delivery_order_id', 'inspected_at', 'inspector_id',
        'overall_result', 'status', 'notes', 'evidence_path',
    ];

    protected function casts(): array
    {
        return ['inspected_at' => 'datetime'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(QualityInspectionResult::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QualityChecklistVersion::class, 'quality_checklist_version_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QualityChecklistTemplate::class, 'quality_checklist_template_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
