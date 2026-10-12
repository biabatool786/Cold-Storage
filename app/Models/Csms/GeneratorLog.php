<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratorLog extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'generator_id', 'logged_at',
        'runtime_hours', 'fuel_quantity', 'fuel_unit',
        'flagged', 'flag_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'runtime_hours' => 'decimal:2',
            'fuel_quantity' => 'decimal:3',
            'flagged' => 'boolean',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(Generator::class);
    }
}
