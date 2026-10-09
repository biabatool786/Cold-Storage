<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\EnergyLog;
use App\Models\Csms\FacilityAsset;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GatePass;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\LaborEntry;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\QualityCheck;
use App\Models\Csms\RateAgreement;
use App\Models\Csms\SensorReading;
use App\Models\Csms\StaffNotice;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Models\Csms\Weighment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\FieldAccess;
use App\Services\Operations\GoodsFlow;
use App\Services\Receiving\ReceivingDesk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CsmsController extends Controller
{
    public function __construct(
        private readonly GoodsFlow $flow,
        private readonly FieldAccess $fields,
        private readonly AuditLogger $audit,
        private readonly ReceivingDesk $receiving,
    ) {}

    public function storeRate(Request $request, Branch $branch): RedirectResponse
    {
        $this->allow($request->user(), $branch, 'customers.create');
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'rate' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);
        $this->sameBranch(Customer::class, (int) $data['customer_id'], $branch);
        RateAgreement::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'customer_id' => $data['customer_id'],
            'rate' => $data['rate'],
            'unit' => $data['unit'] ?? 'kg',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Rate agreement saved.');
    }

    public function commodities(Request $request): View
    {
        return $this->board($request, 'commodities', Commodity::class, 'Products', ['name' => 'Product', 'variety' => 'Variety', 'grade' => 'Grade'], 'products.create', false);
    }

    public function storeCommodity(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('products.create'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'variety' => ['nullable', 'string', 'max:255'], 'grade' => ['nullable', 'string', 'max:255']]);
        Commodity::query()->create([...$data, 'created_by' => $request->user()->id, 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('status', 'Product saved.');
    }

    public function chambers(Request $request): View
    {
        return $this->board($request, 'chambers', Chamber::class, 'Chambers', ['name' => 'Chamber', 'code' => 'Code', 'status' => 'Status'], 'chambers.create');
    }

    public function storeChamber(Request $request, Branch $branch): RedirectResponse
    {
        return $this->store($request, $branch, Chamber::class, 'chambers.create', [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'set_point' => ['nullable', 'numeric'],
        ], 'chambers');
    }

    public function bookings(Request $request): View
    {
        return $this->board($request, 'bookings', Booking::class, 'Bookings', ['id' => 'Booking', 'quantity' => 'Quantity', 'status' => 'Status'], 'bookings.create');
    }

    public function storeBooking(Request $request, Branch $branch): RedirectResponse
    {
        $this->allow($request->user(), $branch, 'bookings.create');
        $data = $request->validate(['customer_id' => ['required', 'integer'], 'commodity_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'min:0.001']]);
        $this->sameBranch(Customer::class, (int) $data['customer_id'], $branch);
        abort_unless(Commodity::query()->whereKey($data['commodity_id'])->exists(), 404);
        Booking::query()->create([...$data, 'tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id, 'status' => 'pending', 'created_by' => $request->user()->id]);

        return back()->with('status', 'Booking requested.');
    }

    public function approveBooking(Booking $booking): RedirectResponse
    {
        $this->flow->approveBooking(request()->user(), $booking);

        return back()->with('status', 'Booking approved.');
    }

    public function gates(Request $request): View
    {
        return $this->board($request, 'gates', GateEntry::class, 'Gate', ['vehicle_no' => 'Vehicle', 'purpose' => 'Purpose', 'status' => 'Status'], 'gate.create');
    }

    public function storeGate(Request $request, Branch $branch): RedirectResponse
    {
        return $this->store($request, $branch, GateEntry::class, 'gate.create', [
            'vehicle_no' => ['required', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'in:inbound,outbound'],
            'customer_id' => ['nullable', 'integer'],
            'booking_id' => ['nullable', 'integer'],
        ], 'gate', ['status' => 'open']);
    }

    public function showGate(GateEntry $gateEntry): View
    {
        $this->see(request()->user(), $gateEntry, 'gate.view');
        $gateEntry->load('weighments', 'inspections', 'goodsReceipts');

        return view('operations.gate', ['entry' => $gateEntry, 'fields' => $this->fields]);
    }

    public function weigh(Request $request, GateEntry $gateEntry): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:gross,tare'],
            'weight' => ['required', 'numeric', 'min:0'],
            'manual' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->flow->recordWeight(
            $request->user(),
            $gateEntry,
            $data['kind'],
            (float) $data['weight'],
            $request->boolean('manual'),
            $data['reason'] ?? null,
        );

        return back()->with('status', 'Weight recorded.');
    }

    public function inspect(Request $request, GateEntry $gateEntry): RedirectResponse
    {
        $data = $request->validate(['result' => ['required', 'in:accept,partial,reject'], 'notes' => ['nullable', 'string', 'max:500']]);
        $this->flow->inspect($request->user(), $gateEntry, $data['result'], $data['notes'] ?? null);

        return back()->with('status', 'Inspection recorded.');
    }

    public function closeGate(GateEntry $gateEntry): RedirectResponse
    {
        $this->flow->closeGate(request()->user(), $gateEntry);

        return back()->with('status', 'Vehicle exited.');
    }

    public function storeGrn(Request $request, GateEntry $gateEntry): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'commodity_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'booking_id' => ['nullable', 'integer'],
        ]);
        $data['submit'] = true;
        $this->receiving->createDraft($request->user(), $gateEntry, $data);

        return back()->with('status', 'GRN submitted for approval.');
    }

    public function approveGrn(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $data = $request->validate(['chamber_id' => ['required', 'integer']]);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $this->flow->approveReceipt($request->user(), $goodsReceipt, $chamber);

        return back()->with('status', 'GRN approved. Complete put-away to store the lot.');
    }

    public function deliveries(Request $request): View
    {
        return $this->board($request, 'deliveries', DeliveryOrder::class, 'Dispatch', ['id' => 'Order', 'quantity' => 'Quantity', 'status' => 'Status'], null);
    }

    public function storeDelivery(Request $request, StorageLot $storageLot): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001']]);
        $this->flow->openDelivery($request->user(), $storageLot, (float) $data['quantity']);

        return back()->with('status', 'Delivery order opened.');
    }

    public function approveDelivery(DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->flow->approveDelivery(request()->user(), $deliveryOrder);

        return back()->with('status', 'Delivery approved.');
    }

    public function issuePass(DeliveryOrder $deliveryOrder): RedirectResponse
    {
        $this->flow->issuePass(request()->user(), $deliveryOrder);

        return back()->with('status', 'Loaded and gate pass issued.');
    }

    public function release(GatePass $gatePass): RedirectResponse
    {
        $this->flow->release(request()->user(), $gatePass);

        return back()->with('status', 'Vehicle released.');
    }

    public function weighments(Request $request): View
    {
        return $this->board($request, 'weighments', Weighment::class, 'Weighbridge', ['kind' => 'Ticket', 'weight' => 'Weight'], null);
    }

    public function monitoring(Request $request): View
    {
        return $this->board($request, 'monitoring', SensorReading::class, 'Monitoring', ['temperature' => 'Temperature', 'humidity' => 'Humidity'], 'monitoring.create');
    }

    public function storeReading(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chamber_id' => ['required', 'integer'],
            'temperature' => ['required', 'numeric'],
            'humidity' => ['nullable', 'numeric'],
        ]);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $this->flow->recordReading($request->user(), $chamber, (float) $data['temperature'], isset($data['humidity']) ? (float) $data['humidity'] : null);

        return back()->with('status', 'Reading stored.');
    }

    public function acknowledge(): RedirectResponse
    {
        abort_unless(request()->user()?->hasPermission('monitoring.acknowledge'), 403);
        $this->audit->record('monitoring.acknowledged', null, [], request()->user()->tenant_id, ['module' => 'monitoring']);

        return back()->with('status', 'Alarm acknowledgement recorded.');
    }

    public function invoices(Request $request): View
    {
        return $this->board($request, 'invoices', StorageInvoice::class, 'Invoices', ['id' => 'Invoice', 'amount' => 'Amount', 'status' => 'Status'], 'billing.create');
    }

    public function storeInvoice(Request $request, Branch $branch): RedirectResponse
    {
        return $this->store($request, $branch, StorageInvoice::class, 'billing.create', [
            'customer_id' => ['required', 'integer'],
            'delivery_order_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
        ], 'billing', ['status' => 'draft']);
    }

    public function postInvoice(StorageInvoice $storageInvoice): RedirectResponse
    {
        $this->flow->postInvoice(request()->user(), $storageInvoice);

        return back()->with('status', 'Invoice posted.');
    }

    public function payments(Request $request): View
    {
        return $this->board($request, 'payments', StoragePayment::class, 'Payments', ['id' => 'Receipt', 'amount' => 'Amount', 'status' => 'Status'], null);
    }

    public function storePayment(Request $request, StorageInvoice $storageInvoice): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('payments.create'), 403);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        StoragePayment::query()->create([
            'tenant_id' => $storageInvoice->tenant_id,
            'branch_id' => $storageInvoice->branch_id,
            'storage_invoice_id' => $storageInvoice->id,
            'amount' => $data['amount'],
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Payment drafted.');
    }

    public function postPayment(StoragePayment $storagePayment): RedirectResponse
    {
        $this->flow->postPayment(request()->user(), $storagePayment);

        return back()->with('status', 'Payment posted.');
    }

    public function expenses(Request $request): View
    {
        return $this->board($request, 'expenses', OperatingExpense::class, 'Expenses', ['description' => 'Expense', 'amount' => 'Amount', 'status' => 'Status'], 'expenses.create');
    }

    public function storeExpense(Request $request, Branch $branch): RedirectResponse
    {
        return $this->store($request, $branch, OperatingExpense::class, 'expenses.create', [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ], 'expenses', ['status' => 'pending']);
    }

    public function approveExpense(OperatingExpense $operatingExpense): RedirectResponse
    {
        $this->flow->approveExpense(request()->user(), $operatingExpense);

        return back()->with('status', 'Expense approved.');
    }

    public function assets(Request $request): View
    {
        return $this->simple($request, FacilityAsset::class, 'Equipment', 'maintenance');
    }

    public function storeAsset(Request $request, Branch $branch): RedirectResponse
    {
        return $this->simpleStore($request, $branch, FacilityAsset::class, 'maintenance.create', 'maintenance');
    }

    public function energy(Request $request): View
    {
        return $this->simple($request, EnergyLog::class, 'Energy', 'energy');
    }

    public function storeEnergy(Request $request, Branch $branch): RedirectResponse
    {
        return $this->simpleStore($request, $branch, EnergyLog::class, 'energy.create', 'energy');
    }

    public function labor(Request $request): View
    {
        return $this->simple($request, LaborEntry::class, 'Labor', 'labor');
    }

    public function storeLabor(Request $request, Branch $branch): RedirectResponse
    {
        return $this->simpleStore($request, $branch, LaborEntry::class, 'labor.create', 'labor');
    }

    public function quality(Request $request): View
    {
        return $this->simple($request, QualityCheck::class, 'Quality', 'quality');
    }

    public function storeQuality(Request $request, Branch $branch): RedirectResponse
    {
        return $this->simpleStore($request, $branch, QualityCheck::class, 'quality.create', 'quality');
    }

    public function notices(Request $request): View
    {
        return $this->simple($request, StaffNotice::class, 'Notices', 'notifications');
    }

    public function storeNotice(Request $request, Branch $branch): RedirectResponse
    {
        return $this->simpleStore($request, $branch, StaffNotice::class, 'notifications.create', 'notifications');
    }

    public function passes(Request $request): View
    {
        return $this->board($request, 'passes', GatePass::class, 'Gate passes', ['id' => 'Pass', 'status' => 'Status'], null);
    }

    private function simple(Request $request, string $model, string $title, string $permission): View
    {
        return $this->board($request, $permission, $model, $title, ['title' => 'Title', 'status' => 'Status'], $permission.'.create');
    }

    private function simpleStore(Request $request, Branch $branch, string $model, string $permission, string $module): RedirectResponse
    {
        return $this->store($request, $branch, $model, $permission, [
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:500'],
        ], $module, ['status' => 'open']);
    }

    private function board(Request $request, string $key, string $model, string $title, array $columns, ?string $createPermission, bool $branched = true): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission($this->viewPermission($key)), 403);
        $query = $model::query()->visibleTo($user)->latest()->limit(50);
        if ($key === 'deliveries') {
            $query->with('gatePasses');
        }
        $records = $query->get();

        return view('operations.board', [
            'title' => $title,
            'columns' => $columns,
            'records' => $records,
            'canCreate' => $createPermission && $user->hasPermission($createPermission),
            'branches' => $branched ? $this->branchesFor($user) : collect(),
            'storeName' => $key.'.store',
            'key' => $key,
            'chambers' => in_array($key, ['grns', 'monitoring'], true)
                ? Chamber::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'branch_id'])
                : collect(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $extra
     */
    private function store(Request $request, Branch $branch, string $model, string $permission, array $rules, string $module, array $extra = []): RedirectResponse
    {
        $this->allow($request->user(), $branch, $permission);
        $data = $request->validate($rules);
        unset($data['tenant_id'], $data['branch_id']);
        if (! empty($data['customer_id'])) {
            $this->sameBranch(Customer::class, (int) $data['customer_id'], $branch);
        }
        if (array_key_exists('credit_limit', $data) && $data['credit_limit'] === null) {
            $data['credit_limit'] = 0;
        }
        $model::query()->create([
            ...$data,
            ...$extra,
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->record($module.'.created', null, [], $branch->tenant_id, ['module' => $module, 'branch_id' => $branch->id, 'new' => $data]);

        return back()->with('status', 'Saved.');
    }

    private function allow(User $user, Branch $branch, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403);
        $this->flow->branchFor($user, $branch);
    }

    private function see(User $user, Model $record, string $permission): void
    {
        abort_unless($user->hasPermission($permission), 403);
        $this->flow->branchFor($user, Branch::query()->findOrFail($record->getAttribute('branch_id')));
    }

    private function sameBranch(string $model, int $id, Branch $branch): void
    {
        abort_unless($model::query()->whereKey($id)->where('branch_id', $branch->id)->exists(), 404);
    }

    private function branchesFor(User $user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(\App\Enums\RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }

    private function viewPermission(string $key): string
    {
        return match ($key) {
            'commodities' => 'products.view',
            'chambers' => 'chambers.view',
            'gates' => 'gate.view',
            'grns' => 'grn.view',
            'lots' => 'inventory.view',
            'deliveries' => 'dispatch.view',
            'weighments' => 'weighbridge.view',
            'monitoring' => 'monitoring.view',
            'invoices' => 'billing.view',
            'passes' => 'gate.view',
            'quality' => 'quality.view',
            'maintenance' => 'maintenance.view',
            default => str_contains($key, '.') ? $key : $key.'.view',
        };
    }
}
