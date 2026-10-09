<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GoodsReceipt extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'gate_entry_id', 'booking_id', 'customer_id', 'commodity_id', 'chamber_id',
        'weighment_id', 'number', 'quantity', 'grade', 'temperature', 'condition_notes', 'loader_count',
        'unloading_notes', 'shortage_qty', 'damage_qty', 'accepted_qty', 'proposed_outcome', 'outcome',
        'outcome_proposed_by', 'outcome_approved_by', 'inspection_notes', 'status', 'created_by', 'approved_by',
        'cancel_reason', 'cancelled_by', 'cancelled_at', 'sent_to_customer_at', 'last_edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'temperature' => 'decimal:2',
            'shortage_qty' => 'decimal:3',
            'damage_qty' => 'decimal:3',
            'accepted_qty' => 'decimal:3',
            'cancelled_at' => 'datetime',
            'sent_to_customer_at' => 'datetime',
        ];
    }

    public function lot(): HasOne
    {
        return $this->hasOne(StorageLot::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GrnPhoto::class);
    }

    public function putAwayTasks(): HasMany
    {
        return $this->hasMany(PutAwayTask::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function gateEntry(): BelongsTo
    {
        return $this->belongsTo(GateEntry::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function weighment(): BelongsTo
    {
        return $this->belongsTo(Weighment::class);
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditableDraft(): bool
    {
        return in_array($this->status, ['draft', 'pending'], true);
    }
}
