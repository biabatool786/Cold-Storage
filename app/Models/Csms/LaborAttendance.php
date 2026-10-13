<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaborAttendance extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $table = 'labor_attendance';

    protected $fillable = [
        'tenant_id', 'branch_id', 'labor_employee_id', 'labor_shift_id',
        'attendance_date', 'status', 'check_in_at', 'check_out_at',
        'subject_type', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(LaborEmployee::class, 'labor_employee_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(LaborAttendanceCorrection::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
