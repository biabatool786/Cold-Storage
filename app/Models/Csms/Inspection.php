<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'gate_entry_id', 'result', 'notes', 'created_by'];
}
