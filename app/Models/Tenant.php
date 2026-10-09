<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'logo',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('current-tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed() || $context->isSuperAdmin()) {
                return;
            }

            if ($context->id() !== null) {
                $builder->whereKey($context->id());

                return;
            }

            $builder->whereRaw('1 = 0');
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
