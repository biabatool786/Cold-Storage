<?php

namespace App\Models\Csms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityChecklistVersion extends Model
{
    protected $fillable = [
        'quality_checklist_template_id', 'version_number', 'items', 'created_by',
    ];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QualityChecklistTemplate::class, 'quality_checklist_template_id');
    }
}
