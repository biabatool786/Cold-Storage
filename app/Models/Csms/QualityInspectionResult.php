<?php

namespace App\Models\Csms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityInspectionResult extends Model
{
    protected $fillable = [
        'quality_inspection_id', 'item_index', 'item_label', 'required', 'response', 'notes',
    ];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class, 'quality_inspection_id');
    }
}
