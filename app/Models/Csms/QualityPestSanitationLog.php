<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class QualityPestSanitationLog extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'log_type', 'logged_on', 'location', 'findings',
        'actions_taken', 'service_provider', 'follow_up_on', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'follow_up_on' => 'date',
        ];
    }

    public function isFollowUpOverdue(): bool
    {
        return $this->follow_up_on && $this->follow_up_on->lt(now()->startOfDay());
    }
}
