<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class OperatingExpense extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'description', 'amount', 'status', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
