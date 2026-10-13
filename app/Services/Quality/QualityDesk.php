<?php

namespace App\Services\Quality;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\QualityCertificate;
use App\Models\Csms\QualityChecklistTemplate;
use App\Models\Csms\QualityChecklistVersion;
use App\Models\Csms\QualityCorrectiveAction;
use App\Models\Csms\QualityIncident;
use App\Models\Csms\QualityInspection;
use App\Models\Csms\QualityInspectionResult;
use App\Models\Csms\QualityInsuranceClaim;
use App\Models\Csms\QualityInsurancePolicy;
use App\Models\Csms\QualityNcr;
use App\Models\Csms\QualityPestSanitationLog;
use App\Models\Csms\QualitySafetyLog;
use App\Models\Csms\QualitySopDocument;
use App\Models\Csms\QualitySopVersion;
use App\Models\Csms\StockLedgerEntry;
use App\Models\Csms\StorageLot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QualityDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
    ) {}

    public function canManageTemplates(User $user): bool
    {
        return $user->hasPermission('quality.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canPerformInspection(User $user): bool
    {
        return $user->hasPermission('quality.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canRaiseNcr(User $user): bool
    {
        return $user->hasPermission('quality.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canAssignCorrective(User $user): bool
    {
        return $user->hasPermission('quality.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canCloseNcr(User $user): bool
    {
        return $user->hasPermission('quality.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canLogPest(User $user): bool
    {
        return $user->hasPermission('quality.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canViewTraceability(User $user): bool
    {
        return $user->hasPermission('quality.view')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Accountant,
            ]));
    }

    public function canPrintTraceability(User $user): bool
    {
        return $user->hasPermission('quality.print')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canManageCertificates(User $user): bool
    {
        return $user->hasPermission('quality.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canViewCertificates(User $user): bool
    {
        return $this->canManageCertificates($user)
            || ($user->hasPermission('quality.view') && $user->hasAnyRole([
                RoleName::Supervisor, RoleName::Accountant,
            ]))
            || $user->isSuperAdmin();
    }

    public function canReportIncident(User $user): bool
    {
        return $user->hasPermission('quality.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canCloseIncident(User $user): bool
    {
        return $user->hasPermission('quality.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canManageInsurancePolicies(User $user): bool
    {
        return ($user->hasPermission('quality.create') || $user->hasPermission('quality.edit'))
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Accountant,
            ]));
    }

    public function canViewInsurancePolicies(User $user): bool
    {
        return $this->canManageInsurancePolicies($user)
            || ($user->hasPermission('quality.view') && $user->hasRole(RoleName::Supervisor))
            || $user->isSuperAdmin();
    }

    public function canCreateInsuranceClaim(User $user): bool
    {
        return $user->hasPermission('quality.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Manager, RoleName::Accountant,
            ]));
    }

    public function canLogSafety(User $user): bool
    {
        return $user->hasPermission('quality.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canManageSop(User $user): bool
    {
        return $user->hasPermission('quality.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canViewSop(User $user): bool
    {
        return $user->hasPermission('quality.view')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
                RoleName::Gatekeeper, RoleName::Accountant,
            ]));
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        abort_unless($this->branches->allows($actor, Branch::query()->findOrFail($branchId)), 403);
    }

    public function createTemplate(User $actor, Branch $branch, array $data): QualityChecklistTemplate
    {
        abort_unless($this->canManageTemplates($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $items = $this->normalizeItems($data['items'] ?? []);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one checklist item is required.']);
        }

        return DB::transaction(function () use ($actor, $branch, $data, $items) {
            $template = QualityChecklistTemplate::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'category' => $data['category'],
                'applicable_process' => $data['applicable_process'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'is_active' => true,
                'current_version' => 1,
                'created_by' => $actor->id,
            ]);
            QualityChecklistVersion::query()->create([
                'quality_checklist_template_id' => $template->id,
                'version_number' => 1,
                'items' => $items,
                'created_by' => $actor->id,
            ]);
            $this->audit->record('quality.template_created', $template, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id,
            ]);

            return $template;
        });
    }

    public function updateTemplate(User $actor, QualityChecklistTemplate $template, array $data): QualityChecklistTemplate
    {
        abort_unless($this->canManageTemplates($actor), 403);
        $this->assertBranchAccess($actor, (int) $template->branch_id);
        $items = $this->normalizeItems($data['items'] ?? []);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one checklist item is required.']);
        }

        return DB::transaction(function () use ($actor, $template, $data, $items) {
            $next = (int) $template->current_version + 1;
            QualityChecklistVersion::query()->create([
                'quality_checklist_template_id' => $template->id,
                'version_number' => $next,
                'items' => $items,
                'created_by' => $actor->id,
            ]);
            $template->update([
                'name' => $data['name'] ?? $template->name,
                'category' => $data['category'] ?? $template->category,
                'applicable_process' => $data['applicable_process'] ?? $template->applicable_process,
                'instructions' => $data['instructions'] ?? $template->instructions,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $template->is_active,
                'current_version' => $next,
            ]);
            $this->audit->record('quality.template_versioned', $template, [], $template->tenant_id, [
                'module' => 'quality', 'branch_id' => $template->branch_id, 'version' => $next,
            ]);

            return $template->fresh();
        });
    }

    public function performInspection(User $actor, Branch $branch, array $data): QualityInspection
    {
        abort_unless($this->canPerformInspection($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $template = QualityChecklistTemplate::query()->findOrFail($data['quality_checklist_template_id']);
        abort_unless((int) $template->tenant_id === (int) $branch->tenant_id, 404);
        abort_unless($template->is_active, 422);
        $version = $template->latestVersion;
        abort_unless($version, 422);
        $items = $version->items ?? [];
        $responses = $data['responses'] ?? [];
        foreach ($items as $i => $item) {
            $required = (bool) ($item['required'] ?? true);
            $response = $responses[$i]['response'] ?? null;
            if ($required && ! in_array($response, ['pass', 'fail', 'na'], true)) {
                throw ValidationException::withMessages([
                    "responses.$i.response" => 'Required checklist item must be answered (pass/fail/na).',
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $branch, $data, $template, $version, $items, $responses) {
            $failCount = 0;
            foreach ($items as $i => $item) {
                if (($responses[$i]['response'] ?? null) === 'fail') {
                    $failCount++;
                }
            }
            $inspection = QualityInspection::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'category' => $template->category,
                'quality_checklist_template_id' => $template->id,
                'quality_checklist_version_id' => $version->id,
                'storage_lot_id' => $data['storage_lot_id'] ?? null,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'chamber_id' => $data['chamber_id'] ?? null,
                'delivery_order_id' => $data['delivery_order_id'] ?? null,
                'inspected_at' => $data['inspected_at'] ?? now(),
                'inspector_id' => $actor->id,
                'overall_result' => $failCount > 0 ? 'fail' : 'pass',
                'status' => 'submitted',
                'notes' => $data['notes'] ?? null,
            ]);
            foreach ($items as $i => $item) {
                QualityInspectionResult::query()->create([
                    'quality_inspection_id' => $inspection->id,
                    'item_index' => $i,
                    'item_label' => $item['label'],
                    'required' => (bool) ($item['required'] ?? true),
                    'response' => $responses[$i]['response'] ?? 'na',
                    'notes' => $responses[$i]['notes'] ?? null,
                ]);
            }
            $this->audit->record('quality.inspection_performed', $inspection, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id, 'version' => $version->version_number,
            ]);

            if ($failCount > 0) {
                app(\App\Services\Notifications\AlertDispatcher::class)->dispatch([
                    'tenant_id' => (int) $branch->tenant_id,
                    'branch_id' => (int) $branch->id,
                    'category' => 'critical_quality',
                    'title' => 'Critical quality inspection failure',
                    'body' => 'Inspection #'.$inspection->id.' failed with '.$failCount.' item(s).',
                    'severity' => 'critical',
                    'reference_type' => QualityInspection::class,
                    'reference_id' => $inspection->id,
                    'idempotency_key' => 'quality-insp-fail:'.$inspection->id,
                ]);
            }

            return $inspection->fresh('results');
        });
    }

    public function raiseNcr(User $actor, Branch $branch, array $data): QualityNcr
    {
        abort_unless($this->canRaiseNcr($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $ref = $data['reference_number'] ?? ('NCR-'.now()->format('YmdHis'));
        if (QualityNcr::query()->where('tenant_id', $branch->tenant_id)->where('reference_number', $ref)->exists()) {
            throw ValidationException::withMessages(['reference_number' => 'NCR reference already exists.']);
        }
        $ncr = QualityNcr::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'reference_number' => $ref,
            'quality_inspection_id' => $data['quality_inspection_id'] ?? null,
            'storage_lot_id' => $data['storage_lot_id'] ?? null,
            'description' => $data['description'],
            'severity' => $data['severity'] ?? 'medium',
            'affected_quantity' => $data['affected_quantity'] ?? null,
            'status' => 'open',
            'reported_by' => $actor->id,
        ]);
        $this->audit->record('quality.ncr_raised', $ncr, [], $branch->tenant_id, [
            'module' => 'quality', 'branch_id' => $branch->id,
        ]);

        return $ncr;
    }

    public function assignCorrectiveAction(User $actor, Branch $branch, array $data): QualityCorrectiveAction
    {
        abort_unless($this->canAssignCorrective($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $action = QualityCorrectiveAction::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'quality_ncr_id' => $data['quality_ncr_id'] ?? null,
            'quality_incident_id' => $data['quality_incident_id'] ?? null,
            'description' => $data['description'],
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'responsible_team' => $data['responsible_team'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
            'assigned_by' => $actor->id,
        ]);
        if (! empty($data['quality_ncr_id'])) {
            QualityNcr::query()->whereKey($data['quality_ncr_id'])->update(['status' => 'corrective_assigned']);
        }
        $this->audit->record('quality.corrective_assigned', $action, [], $branch->tenant_id, [
            'module' => 'quality', 'branch_id' => $branch->id,
        ]);

        return $action;
    }

    public function requestNcrClosure(User $actor, QualityNcr $ncr, string $evidence): void
    {
        abort_unless($this->canRaiseNcr($actor) || $this->canAssignCorrective($actor) || $this->canCloseNcr($actor), 403);
        $this->assertBranchAccess($actor, (int) $ncr->branch_id);
        abort_unless(in_array($ncr->status, ['open', 'corrective_assigned'], true), 422);
        $ncr->update([
            'status' => 'pending_closure',
            'closure_requested_by' => $actor->id,
            'closure_evidence' => $evidence,
        ]);
        $this->audit->record('quality.ncr_closure_requested', $ncr, [], $ncr->tenant_id, [
            'module' => 'quality', 'branch_id' => $ncr->branch_id,
        ]);
    }

    public function decideNcrClosure(User $actor, QualityNcr $ncr, bool $approve): void
    {
        abort_unless($this->canCloseNcr($actor), 403);
        $this->assertBranchAccess($actor, (int) $ncr->branch_id);
        abort_unless($ncr->status === 'pending_closure', 422);
        $this->makerChecker->assertCanApprove($actor, $ncr->closure_requested_by ?? $ncr->reported_by);
        $ncr->update([
            'status' => $approve ? 'closed' : 'open',
            'closed_by' => $approve ? $actor->id : null,
            'closed_at' => $approve ? now() : null,
        ]);
        $this->audit->record($approve ? 'quality.ncr_closed' : 'quality.ncr_closure_rejected', $ncr, [], $ncr->tenant_id, [
            'module' => 'quality', 'branch_id' => $ncr->branch_id,
        ]);
    }

    public function logPestSanitation(User $actor, Branch $branch, array $data): QualityPestSanitationLog
    {
        abort_unless($this->canLogPest($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $log = QualityPestSanitationLog::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'log_type' => $data['log_type'],
            'logged_on' => $data['logged_on'] ?? now()->toDateString(),
            'location' => $data['location'] ?? null,
            'findings' => $data['findings'] ?? null,
            'actions_taken' => $data['actions_taken'] ?? null,
            'service_provider' => $data['service_provider'] ?? null,
            'follow_up_on' => $data['follow_up_on'] ?? null,
            'recorded_by' => $actor->id,
        ]);
        $this->audit->record('quality.pest_sanitation_logged', $log, [], $branch->tenant_id, [
            'module' => 'quality', 'branch_id' => $branch->id,
        ]);

        return $log;
    }

    /**
     * @return array{lot: StorageLot, backward: array<string, mixed>, forward: array<string, mixed>, unavailable: list<string>}
     */
    public function traceLot(User $actor, StorageLot $lot): array
    {
        abort_unless($this->canViewTraceability($actor), 403);
        $this->assertBranchAccess($actor, (int) $lot->branch_id);
        $unavailable = [];
        $lot->load(['receipt', 'chamber', 'customer', 'commodity', 'ledgerEntries']);

        $backward = [
            'goods_receipt' => $lot->receipt ? [
                'id' => $lot->receipt->id,
                'number' => $lot->receipt->number ?? $lot->receipt->id,
                'received_at' => optional($lot->receipt->created_at)?->toDateTimeString(),
            ] : null,
            'customer' => $lot->customer?->name,
            'commodity' => $lot->commodity?->name,
            'chamber' => $lot->chamber?->name,
            'received_at' => optional($lot->received_at)?->toDateTimeString(),
        ];
        if (! $lot->receipt) {
            $unavailable[] = 'Goods receipt / source receiving record unavailable for this lot.';
        }

        $movements = $lot->ledgerEntries()
            ->orderBy('id')
            ->get(['id', 'type', 'quantity', 'balance_after', 'reason', 'created_at', 'reference_type', 'reference_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'quantity' => (string) $e->quantity,
                'balance_after' => (string) $e->balance_after,
                'reason' => $e->reason,
                'at' => optional($e->created_at)?->toDateTimeString(),
                'reference' => $e->reference_type ? ($e->reference_type.'#'.$e->reference_id) : null,
            ])->all();

        $dispatches = DeliveryOrder::query()
            ->where('storage_lot_id', $lot->id)
            ->orderBy('id')
            ->get(['id', 'number', 'quantity', 'dispatched_qty', 'status', 'customer_id'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'number' => $d->number,
                'quantity' => (string) $d->quantity,
                'dispatched_qty' => (string) $d->dispatched_qty,
                'status' => $d->status,
            ])->all();

        if ($movements === [] && $dispatches === []) {
            $unavailable[] = 'No subsequent stock movements or dispatch records found.';
        }

        return [
            'lot' => $lot,
            'backward' => $backward,
            'forward' => [
                'movements' => $movements,
                'dispatches' => $dispatches,
            ],
            'unavailable' => $unavailable,
        ];
    }

    public function saveCertificate(User $actor, Branch $branch, array $data, ?UploadedFile $file = null, ?QualityCertificate $existing = null): QualityCertificate
    {
        abort_unless($this->canManageCertificates($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $path = $existing?->attachment_path;
        $name = $existing?->attachment_name;
        if ($file) {
            $path = $file->store('quality/certificates/'.$branch->tenant_id, 'local');
            $name = $file->getClientOriginalName();
        }
        $payload = [
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'document_type' => $data['document_type'],
            'reference_number' => $data['reference_number'],
            'issuer' => $data['issuer'] ?? null,
            'applicable_entity' => $data['applicable_entity'] ?? null,
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'status' => $data['status'] ?? 'active',
            'attachment_path' => $path,
            'attachment_name' => $name,
        ];
        if ($existing) {
            $existing->update($payload);
            $cert = $existing->fresh();
            $this->audit->record('quality.certificate_updated', $cert, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id,
            ]);
        } else {
            $payload['created_by'] = $actor->id;
            $cert = QualityCertificate::query()->create($payload);
            $this->audit->record('quality.certificate_created', $cert, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id,
            ]);
        }

        return $cert;
    }

    public function reportIncident(User $actor, Branch $branch, array $data): QualityIncident
    {
        abort_unless($this->canReportIncident($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $incident = QualityIncident::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'location' => $data['location'] ?? null,
            'category' => $data['category'] ?? null,
            'description' => $data['description'],
            'severity' => $data['severity'] ?? 'medium',
            'people_assets_lots' => $data['people_assets_lots'] ?? null,
            'immediate_actions' => $data['immediate_actions'] ?? null,
            'status' => 'open',
            'reported_by' => $actor->id,
        ]);
        $this->audit->record('quality.incident_reported', $incident, [], $branch->tenant_id, [
            'module' => 'quality', 'branch_id' => $branch->id,
        ]);

        return $incident;
    }

    public function requestIncidentClosure(User $actor, QualityIncident $incident, ?string $notes = null): void
    {
        abort_unless($this->canReportIncident($actor) || $this->canCloseIncident($actor), 403);
        $this->assertBranchAccess($actor, (int) $incident->branch_id);
        abort_unless($incident->status === 'open', 422);
        $incident->update([
            'status' => 'pending_closure',
            'closure_requested_by' => $actor->id,
            'investigation_notes' => $notes ?: $incident->investigation_notes,
        ]);
        $this->audit->record('quality.incident_closure_requested', $incident, [], $incident->tenant_id, [
            'module' => 'quality', 'branch_id' => $incident->branch_id,
        ]);
    }

    public function decideIncidentClosure(User $actor, QualityIncident $incident, bool $approve): void
    {
        abort_unless($this->canCloseIncident($actor), 403);
        $this->assertBranchAccess($actor, (int) $incident->branch_id);
        abort_unless($incident->status === 'pending_closure', 422);
        $this->makerChecker->assertCanApprove($actor, $incident->closure_requested_by ?? $incident->reported_by);
        $incident->update([
            'status' => $approve ? 'closed' : 'open',
            'closed_by' => $approve ? $actor->id : null,
            'closed_at' => $approve ? now() : null,
        ]);
        $this->audit->record($approve ? 'quality.incident_closed' : 'quality.incident_closure_rejected', $incident, [], $incident->tenant_id, [
            'module' => 'quality', 'branch_id' => $incident->branch_id,
        ]);
    }

    public function saveInsurancePolicy(User $actor, Branch $branch, array $data, ?QualityInsurancePolicy $existing = null): QualityInsurancePolicy
    {
        abort_unless($this->canManageInsurancePolicies($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $payload = [
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'insurer' => $data['insurer'],
            'policy_number' => $data['policy_number'],
            'coverage_type' => $data['coverage_type'] ?? null,
            'insured_asset' => $data['insured_asset'] ?? null,
            'coverage_limit' => $data['coverage_limit'] ?? null,
            'premium_amount' => $data['premium_amount'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
        if ($existing) {
            abort_unless((int) $existing->tenant_id === (int) $branch->tenant_id, 404);
            $existing->update($payload);
            $policy = $existing->fresh();
            $this->audit->record('quality.insurance_policy_updated', $policy, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id,
            ]);
        } else {
            $payload['created_by'] = $actor->id;
            $policy = QualityInsurancePolicy::query()->create($payload);
            $this->audit->record('quality.insurance_policy_created', $policy, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id,
            ]);
        }

        return $policy;
    }

    public function createInsuranceClaim(User $actor, Branch $branch, array $data): QualityInsuranceClaim
    {
        abort_unless($this->canCreateInsuranceClaim($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $policy = QualityInsurancePolicy::query()->findOrFail($data['quality_insurance_policy_id']);
        abort_unless((int) $policy->tenant_id === (int) $branch->tenant_id, 404);
        if (! empty($data['quality_incident_id'])) {
            $incident = QualityIncident::query()->findOrFail($data['quality_incident_id']);
            abort_unless((int) $incident->tenant_id === (int) $branch->tenant_id, 404);
            $this->assertBranchAccess($actor, (int) $incident->branch_id);
        }
        $claim = QualityInsuranceClaim::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'quality_insurance_policy_id' => $policy->id,
            'quality_incident_id' => $data['quality_incident_id'] ?? null,
            'storage_lot_id' => $data['storage_lot_id'] ?? null,
            'claim_reference' => $data['claim_reference'],
            'event_date' => $data['event_date'] ?? null,
            'claim_amount' => $data['claim_amount'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'created_by' => $actor->id,
        ]);
        abort_unless(! in_array($claim->status, ['paid', 'settled'], true) || filled($data['outcome'] ?? null), 422);
        $this->audit->record('quality.insurance_claim_created', $claim, [], $branch->tenant_id, [
            'module' => 'quality', 'branch_id' => $branch->id,
        ]);

        return $claim;
    }

    public function logSafety(User $actor, Branch $branch, array $data): QualitySafetyLog
    {
        abort_unless($this->canLogSafety($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $log = QualitySafetyLog::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'log_type' => $data['log_type'],
            'checked_on' => $data['checked_on'] ?? now()->toDateString(),
            'equipment_label' => $data['equipment_label'] ?? null,
            'chamber_id' => $data['chamber_id'] ?? null,
            'facility_asset_id' => $data['facility_asset_id'] ?? null,
            'result' => $data['result'] ?? 'pass',
            'findings' => $data['findings'] ?? null,
            'follow_up_actions' => $data['follow_up_actions'] ?? null,
            'next_due_on' => $data['next_due_on'] ?? null,
            'recorded_by' => $actor->id,
        ]);
        $this->audit->record('quality.safety_logged', $log, [], $branch->tenant_id, [
            'module' => 'quality', 'branch_id' => $branch->id,
        ]);

        return $log;
    }

    public function saveSop(User $actor, Branch $branch, array $data, ?UploadedFile $file = null, ?QualitySopDocument $existing = null): QualitySopDocument
    {
        abort_unless($this->canManageSop($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        return DB::transaction(function () use ($actor, $branch, $data, $file, $existing) {
            if ($existing) {
                $this->assertBranchAccess($actor, (int) ($existing->branch_id ?: $branch->id));
                $next = (int) $existing->current_version + 1;
                QualitySopVersion::query()->where('quality_sop_document_id', $existing->id)->update(['is_current' => false]);
                $path = null;
                $name = null;
                if ($file) {
                    $path = $file->store('quality/sops/'.$branch->tenant_id, 'local');
                    $name = $file->getClientOriginalName();
                }
                QualitySopVersion::query()->create([
                    'quality_sop_document_id' => $existing->id,
                    'version_number' => $next,
                    'effective_on' => $data['effective_on'] ?? null,
                    'review_on' => $data['review_on'] ?? null,
                    'attachment_path' => $path,
                    'attachment_name' => $name,
                    'notes' => $data['notes'] ?? null,
                    'is_current' => true,
                    'uploaded_by' => $actor->id,
                ]);
                $existing->update([
                    'title' => $data['title'] ?? $existing->title,
                    'category' => $data['category'] ?? $existing->category,
                    'current_version' => $next,
                    'status' => $data['status'] ?? $existing->status,
                ]);
                $this->audit->record('quality.sop_versioned', $existing, [], $branch->tenant_id, [
                    'module' => 'quality', 'branch_id' => $branch->id, 'version' => $next,
                ]);

                return $existing->fresh('versions');
            }

            $doc = QualitySopDocument::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'title' => $data['title'],
                'category' => $data['category'] ?? null,
                'current_version' => 1,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);
            $path = $file ? $file->store('quality/sops/'.$branch->tenant_id, 'local') : null;
            QualitySopVersion::query()->create([
                'quality_sop_document_id' => $doc->id,
                'version_number' => 1,
                'effective_on' => $data['effective_on'] ?? null,
                'review_on' => $data['review_on'] ?? null,
                'attachment_path' => $path,
                'attachment_name' => $file?->getClientOriginalName(),
                'notes' => $data['notes'] ?? null,
                'is_current' => true,
                'uploaded_by' => $actor->id,
            ]);
            $this->audit->record('quality.sop_created', $doc, [], $branch->tenant_id, [
                'module' => 'quality', 'branch_id' => $branch->id,
            ]);

            return $doc->fresh('versions');
        });
    }

    public function sopAttachmentPath(User $actor, QualitySopVersion $version): string
    {
        abort_unless($this->canViewSop($actor), 403);
        $doc = $version->document;
        abort_unless($doc, 404);
        if ($doc->branch_id) {
            $this->assertBranchAccess($actor, (int) $doc->branch_id);
        }
        abort_unless($version->attachment_path && Storage::disk('local')->exists($version->attachment_path), 404);
        $this->audit->record('quality.sop_attachment_viewed', $doc, [], $doc->tenant_id, [
            'module' => 'quality', 'version' => $version->version_number,
        ]);

        return Storage::disk('local')->path($version->attachment_path);
    }

    /**
     * @param  mixed  $items
     * @return list<array{label: string, required: bool}>
     */
    private function normalizeItems(mixed $items): array
    {
        if (is_string($items)) {
            $lines = preg_split('/\r\n|\r|\n/', $items) ?: [];
            $out = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $required = ! str_starts_with($line, '?');
                $label = ltrim($line, '? ');
                $out[] = ['label' => $label, 'required' => $required];
            }

            return $out;
        }
        if (! is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = ['label' => trim($item), 'required' => true];
            } elseif (is_array($item) && filled($item['label'] ?? null)) {
                $out[] = ['label' => (string) $item['label'], 'required' => (bool) ($item['required'] ?? true)];
            }
        }

        return $out;
    }
}
