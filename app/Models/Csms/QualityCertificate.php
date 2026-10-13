<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class QualityCertificate extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'document_type', 'reference_number', 'issuer',
        'applicable_entity', 'issued_on', 'expires_on', 'status',
        'attachment_path', 'attachment_name', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function expiryStatus(): string
    {
        if (! $this->expires_on) {
            return $this->status ?: 'active';
        }
        if ($this->expires_on->lt(now()->startOfDay())) {
            return 'expired';
        }
        if ($this->expires_on->lte(now()->addDays(30)->startOfDay())) {
            return 'expiring_soon';
        }

        return 'active';
    }
}
