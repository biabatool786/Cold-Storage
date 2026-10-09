<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRevision extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'branch_id', 'booking_id', 'rate', 'rate_agreement_id', 'status', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
