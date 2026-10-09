<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'commodity_id', 'commodity_variety_id', 'commodity_grade_id',
        'chamber_id', 'rate_agreement_id', 'quantity', 'rate', 'starts_on', 'ends_on', 'reference', 'notes',
        'status', 'created_by', 'approved_by', 'cancelled_by', 'cancelled_at', 'cancel_reason',
        'vehicle_no', 'driver_name',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'rate' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function commodity()
    {
        return $this->belongsTo(Commodity::class);
    }

    public function variety()
    {
        return $this->belongsTo(CommodityVariety::class, 'commodity_variety_id');
    }

    public function grade()
    {
        return $this->belongsTo(CommodityGrade::class, 'commodity_grade_id');
    }

    public function chamber()
    {
        return $this->belongsTo(Chamber::class);
    }

    public function rateAgreement()
    {
        return $this->belongsTo(RateAgreement::class);
    }

    public function deposits()
    {
        return $this->hasMany(BookingDeposit::class);
    }

    public function revisions()
    {
        return $this->hasMany(BookingRevision::class);
    }
}
