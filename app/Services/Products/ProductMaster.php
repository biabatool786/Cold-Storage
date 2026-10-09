<?php

namespace App\Services\Products;

use App\Enums\RoleName;
use App\Models\Csms\Commodity;
use App\Models\Csms\CommodityGrade;
use App\Models\Csms\CommodityIncompatibility;
use App\Models\Csms\CommodityShrinkageChange;
use App\Models\Csms\CommodityUnit;
use App\Models\Csms\CommodityVariety;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use App\Services\Tenancy\TenantContext;

class ProductMaster
{
    public const UNITS = ['bag', 'kg', 'pallet'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
        private readonly TenantContext $tenants,
    ) {}

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('products.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canEditIdentity(User $user): bool
    {
        return $user->hasPermission('products.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canEditStorage(User $user): bool
    {
        return $user->hasPermission('products.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canViewUnits(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Accountant]);
    }

    public function canEditUnits(User $user): bool
    {
        return $user->hasPermission('products.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canEditRules(User $user): bool
    {
        return $user->hasPermission('products.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canProposeShrinkage(User $user): bool
    {
        return $user->hasPermission('products.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canApproveShrinkage(User $user): bool
    {
        return $user->hasPermission('products.approve') && $user->hasRole(RoleName::Manager);
    }

    public function canDeactivate(User $user): bool
    {
        return $user->hasPermission('products.edit') && $user->hasRole(RoleName::Admin);
    }

    public function canDelete(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('products.delete');
    }

    public function create(User $actor, array $data, ?Tenant $tenant = null): Commodity
    {
        abort_unless($this->canCreate($actor), 403);
        abort_if($actor->isSuperAdmin() && $tenant === null, 403);
        unset($data['tenant_id'], $data['branch_id']);
        $commodity = Commodity::query()->create([
            'tenant_id' => $actor->isSuperAdmin() ? $tenant->id : $this->tenants->id(),
            'name' => $data['name'],
            'code' => filled($data['code'] ?? null) ? $data['code'] : null,
            'variety' => $data['variety'] ?? null,
            'grade' => $data['grade'] ?? null,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
        abort_if($commodity->tenant_id === null, 403);
        if (! empty($data['variety'])) {
            $variety = $this->makeVariety($actor, $commodity, (string) $data['variety'], (string) ($data['variety_code'] ?? $data['variety']));
            if (! empty($data['grade'])) {
                $this->makeGrade($actor, $variety, (string) $data['grade'], (string) ($data['grade_code'] ?? $data['grade']));
            }
        }
        $this->audit->record('product.created', $commodity, ['name' => $commodity->name], $commodity->tenant_id, [
            'module' => 'products',
        ]);

        return $commodity;
    }

    public function update(User $actor, Commodity $commodity, array $data): void
    {
        abort_unless($this->canEditIdentity($actor), 403);
        $old = $commodity->only(['name', 'code']);
        $commodity->update(['name' => $data['name'], 'code' => $data['code'] ?? $commodity->code]);
        $this->audit->record('product.updated', $commodity, [], $commodity->tenant_id, [
            'module' => 'products', 'old' => $old, 'new' => $commodity->only(['name', 'code']),
        ]);
    }

    public function addVariety(User $actor, Commodity $commodity, string $name, string $code): CommodityVariety
    {
        abort_unless($this->canCreate($actor), 403);
        $variety = $this->makeVariety($actor, $commodity, $name, $code);
        $this->audit->record('product.variety_created', $variety, ['code' => $code], $commodity->tenant_id, ['module' => 'products']);

        return $variety;
    }

    public function updateVariety(User $actor, CommodityVariety $variety, string $name): void
    {
        abort_unless($this->canEditIdentity($actor), 403);
        $variety->update(['name' => $name]);
        $this->audit->record('product.variety_updated', $variety, ['name' => $name], $variety->tenant_id, ['module' => 'products']);
    }

    public function addGrade(User $actor, CommodityVariety $variety, string $name, string $code): CommodityGrade
    {
        abort_unless($this->canCreate($actor), 403);
        $grade = $this->makeGrade($actor, $variety, $name, $code);
        $this->audit->record('product.grade_created', $grade, ['code' => $code], $variety->tenant_id, ['module' => 'products']);

        return $grade;
    }

    public function updateGrade(User $actor, CommodityGrade $grade, string $name): void
    {
        abort_unless($this->canEditIdentity($actor), 403);
        $grade->update(['name' => $name]);
        $this->audit->record('product.grade_updated', $grade, ['name' => $name], $grade->tenant_id, ['module' => 'products']);
    }

    public function storeConditions(User $actor, Commodity $commodity, array $data): void
    {
        abort_unless($this->canEditStorage($actor), 403);
        $commodity->update([
            'storage_temperature' => $data['storage_temperature'] ?? null,
            'storage_humidity' => $data['storage_humidity'] ?? null,
            'shelf_life_days' => $data['shelf_life_days'] ?? null,
        ]);
        $this->audit->record('product.storage_updated', $commodity, [], $commodity->tenant_id, [
            'module' => 'products',
            'new' => $commodity->only(['storage_temperature', 'storage_humidity', 'shelf_life_days']),
        ]);
    }

    public function saveUnit(User $actor, Commodity $commodity, string $unit, float $factor): void
    {
        abort_unless($this->canEditUnits($actor), 403);
        abort_unless(in_array($unit, self::UNITS, true), 422);
        if ($unit === 'kg') {
            $factor = 1;
        }
        $row = CommodityUnit::query()->updateOrCreate(
            ['commodity_id' => $commodity->id, 'unit' => $unit],
            ['tenant_id' => $commodity->tenant_id, 'factor_to_kg' => $factor],
        );
        $this->audit->record('product.unit_updated', $row, ['unit' => $unit, 'factor_to_kg' => $factor], $commodity->tenant_id, [
            'module' => 'products',
        ]);
    }

    public function addIncompatible(User $actor, Commodity $commodity, Commodity $other): void
    {
        abort_unless($this->canEditRules($actor), 403);
        abort_if($commodity->id === $other->id || $commodity->tenant_id !== $other->tenant_id, 422);
        $exists = CommodityIncompatibility::query()
            ->where('commodity_id', $other->id)
            ->where('incompatible_commodity_id', $commodity->id)
            ->exists()
            || CommodityIncompatibility::query()
                ->where('commodity_id', $commodity->id)
                ->where('incompatible_commodity_id', $other->id)
                ->exists();
        abort_if($exists, 422);
        $rule = CommodityIncompatibility::query()->create([
            'tenant_id' => $commodity->tenant_id,
            'commodity_id' => $commodity->id,
            'incompatible_commodity_id' => $other->id,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('product.incompatibility_created', $rule, ['other' => $other->id], $commodity->tenant_id, [
            'module' => 'products',
        ]);
    }

    public function proposeShrinkage(User $actor, Commodity $commodity, float $percent): void
    {
        abort_unless($this->canProposeShrinkage($actor), 403);
        $change = CommodityShrinkageChange::query()->create([
            'tenant_id' => $commodity->tenant_id,
            'commodity_id' => $commodity->id,
            'percent' => $percent,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('product.shrinkage_proposed', $change, ['percent' => $percent], $commodity->tenant_id, [
            'module' => 'products',
        ]);
    }

    public function decideShrinkage(User $actor, CommodityShrinkageChange $change, bool $approve): void
    {
        abort_unless($this->canApproveShrinkage($actor), 403);
        abort_unless($change->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $change->created_by);
        $change->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $actor->id]);
        if ($approve) {
            $change->commodity->update(['shrinkage_percent' => $change->percent]);
        }
        $this->audit->record($approve ? 'product.shrinkage_approved' : 'product.shrinkage_rejected', $change, [], $change->tenant_id, [
            'module' => 'products',
        ]);
    }

    public function deactivate(User $actor, Commodity $commodity): void
    {
        abort_unless($this->canDeactivate($actor), 403);
        $commodity->update(['status' => 'inactive']);
        $this->audit->record('product.deactivated', $commodity, [], $commodity->tenant_id, ['module' => 'products']);
    }

    public function remove(User $actor, Commodity $commodity, ?string $reason): void
    {
        abort_unless($this->canDelete($actor), 403);
        $this->reasons->assert('delete', $reason);
        $commodity->delete();
        $this->audit->record('product.deleted', $commodity, ['reason' => $reason], $commodity->tenant_id, ['module' => 'products']);
    }

    private function makeVariety(User $actor, Commodity $commodity, string $name, string $code): CommodityVariety
    {
        return CommodityVariety::query()->create([
            'tenant_id' => $commodity->tenant_id,
            'commodity_id' => $commodity->id,
            'name' => $name,
            'code' => $code,
            'created_by' => $actor->id,
        ]);
    }

    private function makeGrade(User $actor, CommodityVariety $variety, string $name, string $code): CommodityGrade
    {
        return CommodityGrade::query()->create([
            'tenant_id' => $variety->tenant_id,
            'commodity_variety_id' => $variety->id,
            'name' => $name,
            'code' => $code,
            'created_by' => $actor->id,
        ]);
    }
}
