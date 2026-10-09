<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLimit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope_key',
        'tenant_id',
        'code',
        'name',
        'manager_limit',
        'unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manager_limit' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function platform(string $code): ?self
    {
        return self::query()->where('scope_key', 'platform')->where('code', $code)->first();
    }
}
