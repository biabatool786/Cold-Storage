<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\QualityCertificate;
use App\Models\Csms\QualityChecklistTemplate;
use App\Models\Csms\QualityCorrectiveAction;
use App\Models\Csms\QualityIncident;
use App\Models\Csms\QualityInspection;
use App\Models\Csms\QualityInsuranceClaim;
use App\Models\Csms\QualityInsurancePolicy;
use App\Models\Csms\QualityNcr;
use App\Models\Csms\QualityPestSanitationLog;
use App\Models\Csms\QualitySafetyLog;
use App\Models\Csms\QualitySopDocument;
use App\Models\Csms\QualitySopVersion;
use App\Models\Csms\StorageLot;
use App\Models\User;
use App\Services\Quality\QualityDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QualityController extends Controller
{
    public function __construct(private readonly QualityDesk $desk) {}

    public function templates(Request $request): View
    {
        abort_unless($this->desk->canManageTemplates($request->user()), 403);
        $user = $request->user();

        return view('quality.templates', [
            'templates' => QualityChecklistTemplate::query()->visibleTo($user)->with('latestVersion')->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeTemplate(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:incoming,periodic,outgoing'],
            'applicable_process' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'items' => ['required', 'string'],
        ]);
        $this->desk->createTemplate($request->user(), $branch, $data);

        return back()->with('status', 'Checklist template created.');
    }

    public function updateTemplate(Request $request, QualityChecklistTemplate $quality_checklist_template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:incoming,periodic,outgoing'],
            'applicable_process' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'items' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->desk->updateTemplate($request->user(), $quality_checklist_template, $data);

        return back()->with('status', 'Template version saved.');
    }

    public function inspections(Request $request): View
    {
        abort_unless($this->desk->canPerformInspection($request->user()), 403);
        $user = $request->user();

        return view('quality.inspections', [
            'inspections' => QualityInspection::query()->visibleTo($user)->with(['template', 'results'])->latest()->limit(40)->get(),
            'templates' => QualityChecklistTemplate::query()->visibleTo($user)->where('is_active', true)->with('latestVersion')->orderBy('name')->get(),
            'lots' => StorageLot::query()->visibleTo($user)->orderByDesc('id')->limit(80)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeInspection(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'quality_checklist_template_id' => ['required', 'integer'],
            'storage_lot_id' => ['nullable', 'integer'],
            'goods_receipt_id' => ['nullable', 'integer'],
            'chamber_id' => ['nullable', 'integer'],
            'delivery_order_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'responses' => ['nullable', 'array'],
        ]);
        $this->desk->performInspection($request->user(), $branch, $data);

        return back()->with('status', 'Inspection submitted.');
    }

    public function ncrs(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canRaiseNcr($user) || $this->desk->canCloseNcr($user) || $this->desk->canAssignCorrective($user), 403);

        return view('quality.ncrs', [
            'ncrs' => QualityNcr::query()->visibleTo($user)->with('actions')->latest()->limit(50)->get(),
            'actions' => QualityCorrectiveAction::query()->visibleTo($user)->latest()->limit(40)->get(),
            'inspections' => QualityInspection::query()->visibleTo($user)->where('overall_result', 'fail')->latest()->limit(40)->get(),
            'users' => User::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeNcr(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:100'],
            'quality_inspection_id' => ['nullable', 'integer'],
            'storage_lot_id' => ['nullable', 'integer'],
            'description' => ['required', 'string'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'affected_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->desk->raiseNcr($request->user(), $branch, $data);

        return back()->with('status', 'NCR raised.');
    }

    public function storeCorrective(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'quality_ncr_id' => ['nullable', 'integer'],
            'quality_incident_id' => ['nullable', 'integer'],
            'description' => ['required', 'string'],
            'responsible_user_id' => ['nullable', 'integer'],
            'responsible_team' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ]);
        $this->desk->assignCorrectiveAction($request->user(), $branch, $data);

        return back()->with('status', 'Corrective action assigned.');
    }

    public function requestNcrClosure(Request $request, QualityNcr $quality_ncr): RedirectResponse
    {
        $data = $request->validate(['closure_evidence' => ['required', 'string']]);
        $this->desk->requestNcrClosure($request->user(), $quality_ncr, $data['closure_evidence']);

        return back()->with('status', 'NCR closure requested.');
    }

    public function decideNcr(Request $request, QualityNcr $quality_ncr): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->decideNcrClosure($request->user(), $quality_ncr, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'NCR closed.' : 'NCR closure rejected.');
    }

    public function pest(Request $request): View
    {
        abort_unless($this->desk->canLogPest($request->user()), 403);
        $user = $request->user();

        return view('quality.pest', [
            'logs' => QualityPestSanitationLog::query()->visibleTo($user)->latest('logged_on')->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storePest(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'log_type' => ['required', 'in:pest_control,sanitation'],
            'logged_on' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'findings' => ['nullable', 'string'],
            'actions_taken' => ['nullable', 'string'],
            'service_provider' => ['nullable', 'string', 'max:255'],
            'follow_up_on' => ['nullable', 'date'],
        ]);
        $this->desk->logPestSanitation($request->user(), $branch, $data);

        return back()->with('status', 'Pest/sanitation log recorded.');
    }

    public function traceability(Request $request): View
    {
        abort_unless($this->desk->canViewTraceability($request->user()), 403);
        $user = $request->user();
        $trace = null;
        if ($request->filled('storage_lot_id')) {
            $lot = StorageLot::query()->findOrFail($request->integer('storage_lot_id'));
            $trace = $this->desk->traceLot($user, $lot);
        }

        return view('quality.traceability', [
            'lots' => StorageLot::query()->visibleTo($user)->orderByDesc('id')->limit(100)->get(),
            'trace' => $trace,
            'desk' => $this->desk,
            'canPrint' => $this->desk->canPrintTraceability($user),
        ]);
    }

    public function printTraceability(Request $request): View
    {
        abort_unless($this->desk->canPrintTraceability($request->user()), 403);
        $lot = StorageLot::query()->findOrFail($request->integer('storage_lot_id'));
        $trace = $this->desk->traceLot($request->user(), $lot);

        return view('quality.traceability-print', ['trace' => $trace]);
    }

    public function certificates(Request $request): View
    {
        abort_unless($this->desk->canViewCertificates($request->user()), 403);
        $user = $request->user();

        return view('quality.certificates', [
            'certificates' => QualityCertificate::query()->visibleTo($user)->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeCertificate(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'reference_number' => ['required', 'string', 'max:100'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'applicable_entity' => ['nullable', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ]);
        $this->desk->saveCertificate($request->user(), $branch, $data, $request->file('attachment'));

        return back()->with('status', 'Certificate saved.');
    }

    public function incidents(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canReportIncident($user) || $this->desk->canCloseIncident($user), 403);

        return view('quality.incidents', [
            'incidents' => QualityIncident::query()->visibleTo($user)->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeIncident(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'occurred_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'people_assets_lots' => ['nullable', 'string'],
            'immediate_actions' => ['nullable', 'string'],
        ]);
        $this->desk->reportIncident($request->user(), $branch, $data);

        return back()->with('status', 'Incident reported.');
    }

    public function requestIncidentClosure(Request $request, QualityIncident $quality_incident): RedirectResponse
    {
        $data = $request->validate(['investigation_notes' => ['nullable', 'string']]);
        $this->desk->requestIncidentClosure($request->user(), $quality_incident, $data['investigation_notes'] ?? null);

        return back()->with('status', 'Incident closure requested.');
    }

    public function decideIncident(Request $request, QualityIncident $quality_incident): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->decideIncidentClosure($request->user(), $quality_incident, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Incident closed.' : 'Incident closure rejected.');
    }

    public function policies(Request $request): View
    {
        abort_unless($this->desk->canViewInsurancePolicies($request->user()), 403);
        $user = $request->user();

        return view('quality.policies', [
            'policies' => QualityInsurancePolicy::query()->visibleTo($user)->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storePolicy(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'insurer' => ['required', 'string', 'max:255'],
            'policy_number' => ['required', 'string', 'max:100'],
            'coverage_type' => ['nullable', 'string', 'max:100'],
            'insured_asset' => ['nullable', 'string', 'max:255'],
            'coverage_limit' => ['nullable', 'numeric', 'min:0'],
            'premium_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
        ]);
        $this->desk->saveInsurancePolicy($request->user(), $branch, $data);

        return back()->with('status', 'Insurance policy saved.');
    }

    public function claims(Request $request): View
    {
        abort_unless($this->desk->canCreateInsuranceClaim($request->user()) || $this->desk->canViewInsurancePolicies($request->user()), 403);
        $user = $request->user();
        abort_unless($this->desk->canCreateInsuranceClaim($user), 403);

        return view('quality.claims', [
            'claims' => QualityInsuranceClaim::query()->visibleTo($user)->with('policy')->latest()->limit(50)->get(),
            'policies' => QualityInsurancePolicy::query()->visibleTo($user)->orderBy('policy_number')->limit(50)->get(),
            'incidents' => QualityIncident::query()->visibleTo($user)->latest()->limit(40)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeClaim(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'quality_insurance_policy_id' => ['required', 'integer'],
            'quality_incident_id' => ['nullable', 'integer'],
            'storage_lot_id' => ['nullable', 'integer'],
            'claim_reference' => ['required', 'string', 'max:100'],
            'event_date' => ['nullable', 'date'],
            'claim_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,submitted,under_review,approved,rejected'],
        ]);
        $this->desk->createInsuranceClaim($request->user(), $branch, $data);

        return back()->with('status', 'Insurance claim created.');
    }

    public function safety(Request $request): View
    {
        abort_unless($this->desk->canLogSafety($request->user()), 403);
        $user = $request->user();

        return view('quality.safety', [
            'logs' => QualitySafetyLog::query()->visibleTo($user)->latest('checked_on')->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeSafety(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'log_type' => ['required', 'in:gas_leak,fire_safety'],
            'checked_on' => ['nullable', 'date'],
            'equipment_label' => ['nullable', 'string', 'max:255'],
            'chamber_id' => ['nullable', 'integer'],
            'facility_asset_id' => ['nullable', 'integer'],
            'result' => ['nullable', 'in:pass,fail'],
            'findings' => ['nullable', 'string'],
            'follow_up_actions' => ['nullable', 'string'],
            'next_due_on' => ['nullable', 'date'],
        ]);
        $this->desk->logSafety($request->user(), $branch, $data);

        return back()->with('status', 'Safety log recorded.');
    }

    public function sops(Request $request): View
    {
        abort_unless($this->desk->canViewSop($request->user()), 403);
        $user = $request->user();

        return view('quality.sops', [
            'documents' => QualitySopDocument::query()->visibleTo($user)->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeSop(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'effective_on' => ['nullable', 'date'],
            'review_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'quality_sop_document_id' => ['nullable', 'integer'],
        ]);
        $existing = null;
        if (! empty($data['quality_sop_document_id'])) {
            $existing = QualitySopDocument::query()->findOrFail($data['quality_sop_document_id']);
        }
        $this->desk->saveSop($request->user(), $branch, $data, $request->file('attachment'), $existing);

        return back()->with('status', 'SOP saved.');
    }

    public function downloadSop(QualitySopVersion $quality_sop_version): BinaryFileResponse
    {
        $path = $this->desk->sopAttachmentPath(request()->user(), $quality_sop_version);

        return response()->download($path, $quality_sop_version->attachment_name ?: 'sop');
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
