<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class StaffAnnouncement extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'title', 'message', 'target_roles',
        'target_branch_ids', 'published_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'target_branch_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
