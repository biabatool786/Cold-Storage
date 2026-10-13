<?php

namespace App\Models\Csms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualitySopVersion extends Model
{
    protected $fillable = [
        'quality_sop_document_id', 'version_number', 'effective_on', 'review_on',
        'attachment_path', 'attachment_name', 'notes', 'is_current', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'review_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(QualitySopDocument::class, 'quality_sop_document_id');
    }
}
