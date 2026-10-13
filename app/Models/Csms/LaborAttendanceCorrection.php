<?php

namespace App\Models\Csms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborAttendanceCorrection extends Model
{
    protected $fillable = [
        'labor_attendance_id', 'original_values', 'corrected_values', 'reason', 'corrected_by',
    ];

    protected function casts(): array
    {
        return [
            'original_values' => 'array',
            'corrected_values' => 'array',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(LaborAttendance::class, 'labor_attendance_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
