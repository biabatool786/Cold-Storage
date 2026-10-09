<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDeposit extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'branch_id', 'booking_id', 'amount', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
