<?php

namespace App\Services\Customers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\CreditLimitChange;
use App\Models\Csms\Customer;
use App\Models\Csms\CustomerDocument;
use App\Models\Csms\CustomerNote;
use App\Models\Csms\RateAgreement;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StoragePayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerMaster
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
        private readonly BranchAccess $branches,
    ) {}

    public function seesNameOnly(User $user): bool
    {
        return $user->hasRole(RoleName::Gatekeeper);
    }

    public function seesFinancials(User $user): bool
    {
        return ! $user->hasRole(RoleName::Gatekeeper) && ! $user->hasRole(RoleName::Supervisor);
    }

    public function canEditBasic(User $user): bool
    {
        return $user->hasPermission('customers.edit') && ! $user->hasRole(RoleName::Accountant);
    }

    public function canEditBilling(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(RoleName::Admin) || $user->hasRole(RoleName::Accountant);
    }

    public function canSetCredit(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(RoleName::Admin);
    }

    public function canProposeCredit(User $user): bool
    {
        return $user->hasRole(RoleName::Accountant);
    }

    public function canApprove(User $user): bool
    {
        return $user->hasPermission('customers.approve');
    }

    public function canHold(User $user): bool
    {
        return $user->hasPermission('customers.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Accountant]));
    }

    public function canRemoveHold(User $user): bool
    {
        return $user->hasPermission('customers.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]));
    }

    public function canNote(User $user): bool
    {
        return ! $user->hasRole(RoleName::Gatekeeper)
            && ($user->hasPermission('customers.create') || $user->hasPermission('customers.execute'));
    }

    public function canMessage(User $user): bool
    {
        return $this->canHold($user);
    }

    public function canImport(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(RoleName::Admin);
    }

    public function guardBranch(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function create(User $actor, Branch $branch, array $data): Customer
    {
        abort_unless($actor->hasPermission('customers.create'), 403);
        $this->guardBranch($actor, $branch);
        $customer = Customer::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'tax_no' => $data['tax_no'] ?? null,
            'billing_name' => $data['billing_name'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'credit_limit' => $this->canSetCredit($actor) ? ($data['credit_limit'] ?? 0) : 0,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('customer.created', $customer, [], $branch->tenant_id, [
            'module' => 'customers',
            'branch_id' => $branch->id,
            'new' => ['name' => $customer->name, 'status' => 'pending'],
        ]);

        return $customer;
    }

    public function update(User $actor, Customer $customer, array $data, ?string $reason): void
    {
        $this->guardBranch($actor, $customer->branch);
        if (in_array($customer->status, ['approved', 'hold', 'blacklisted', 'active'], true)) {
            $this->reasons->assert('edit_after_approval', $reason);
        }
        $basic = $this->canEditBasic($actor) ? array_intersect_key($data, array_flip(['name', 'phone', 'email', 'address', 'city'])) : [];
        $billing = $this->canEditBilling($actor) ? array_intersect_key($data, array_flip(['billing_name', 'billing_address', 'tax_no'])) : [];
        abort_if($basic === [] && $billing === [], 403);
        $old = $customer->only(array_keys($basic + $billing));
        $customer->update($basic + $billing);
        $this->audit->record('customer.updated', $customer, [], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
            'reason' => $reason,
            'old' => $old,
            'new' => $customer->only(array_keys($basic + $billing)),
        ]);
    }

    public function approve(User $actor, Customer $customer): void
    {
        abort_unless($this->canApprove($actor), 403);
        $this->guardBranch($actor, $customer->branch);
        abort_unless($customer->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $customer->created_by);
        $customer->update(['status' => 'approved', 'approved_by' => $actor->id, 'status_changed_at' => now()]);
        $this->audit->record('customer.approved', $customer, [], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
            'new' => ['status' => 'approved'],
        ]);
    }

    public function setCredit(User $actor, Customer $customer, float $amount, ?string $reason): void
    {
        $this->guardBranch($actor, $customer->branch);
        if ($this->canSetCredit($actor)) {
            if (in_array($customer->status, ['approved', 'hold', 'blacklisted', 'active'], true)) {
                $this->reasons->assert('edit_after_approval', $reason);
            }
            $old = $customer->credit_limit;
            $customer->update(['credit_limit' => $amount]);
            $this->audit->record('customer.credit_updated', $customer, [], $customer->tenant_id, [
                'module' => 'customers',
                'branch_id' => $customer->branch_id,
                'reason' => $reason,
                'old' => ['credit_limit' => $old],
                'new' => ['credit_limit' => $amount],
            ]);

            return;
        }

        abort_unless($this->canProposeCredit($actor), 403);
        CreditLimitChange::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'status' => 'pending',
            'created_by' => $actor->id,
            'reason' => $reason,
        ]);
        $this->audit->record('customer.credit_proposed', $customer, ['amount' => $amount], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
        ]);
    }

    public function approveCredit(User $actor, CreditLimitChange $change): void
    {
        abort_unless($actor->hasPermission('customers.approve') && $actor->hasRole(RoleName::Manager) || $actor->isSuperAdmin(), 403);
        abort_unless($change->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $change->created_by);
        $customer = Customer::query()->findOrFail($change->customer_id);
        $this->guardBranch($actor, $customer->branch);
        $change->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $customer->update(['credit_limit' => $change->amount]);
        $this->audit->record('customer.credit_approved', $customer, ['amount' => $change->amount], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
            'new' => ['credit_limit' => $change->amount],
        ]);
    }

    public function hold(User $actor, Customer $customer, string $status, string $reason): void
    {
        abort_unless($this->canHold($actor), 403);
        abort_unless(in_array($status, ['hold', 'blacklisted'], true), 422);
        $this->guardBranch($actor, $customer->branch);
        $this->reasons->assert('override', $reason);
        $customer->update(['status' => $status, 'hold_reason' => $reason, 'status_changed_at' => now()]);
        $this->audit->record('customer.'.$status, $customer, [], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
            'reason' => $reason,
            'new' => ['status' => $status],
        ]);
    }

    public function release(User $actor, Customer $customer, string $reason): void
    {
        abort_unless($this->canRemoveHold($actor), 403);
        $this->guardBranch($actor, $customer->branch);
        if ($actor->hasRole(RoleName::Accountant) && ! $this->paymentsCleared($customer)) {
            throw ValidationException::withMessages(['status' => 'Hold can be removed after posted invoices are paid.']);
        }
        $this->reasons->assert('override', $reason);
        $customer->update(['status' => 'approved', 'hold_reason' => null, 'status_changed_at' => now()]);
        $this->audit->record('customer.hold_removed', $customer, [], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
            'reason' => $reason,
        ]);
    }

    public function paymentsCleared(Customer $customer): bool
    {
        $invoiced = (float) StorageInvoice::query()->where('customer_id', $customer->id)->where('status', 'posted')->sum('amount');
        $paid = (float) StoragePayment::query()->where('status', 'posted')->whereIn(
            'storage_invoice_id',
            StorageInvoice::query()->where('customer_id', $customer->id)->pluck('id'),
        )->sum('amount');

        return $paid + 0.009 >= $invoiced;
    }

    public function storeDocument(User $actor, Customer $customer, UploadedFile $file): void
    {
        abort_unless($actor->hasPermission('customers.create'), 403);
        $this->guardBranch($actor, $customer->branch);
        $path = $file->store('kyc/'.$customer->tenant_id);
        $document = CustomerDocument::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('customer.kyc_uploaded', $document, ['name' => $document->original_name], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
        ]);
    }

    public function viewDocument(User $actor, CustomerDocument $document): string
    {
        abort_unless($actor->hasPermission('customers.view'), 403);
        $customer = Customer::query()->findOrFail($document->customer_id);
        $this->guardBranch($actor, $customer->branch);
        $this->audit->record('customer.kyc_viewed', $document, [], $document->tenant_id, [
            'module' => 'customers',
            'branch_id' => $document->branch_id,
        ]);

        return Storage::path($document->path);
    }

    public function note(User $actor, Customer $customer, string $body): void
    {
        abort_unless($this->canNote($actor), 403);
        $this->guardBranch($actor, $customer->branch);
        CustomerNote::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'body' => $body,
            'channel' => 'note',
            'status' => 'saved',
            'created_by' => $actor->id,
        ]);
    }

    public function message(User $actor, Customer $customer, string $channel, string $body): CustomerNote
    {
        abort_unless($this->canMessage($actor), 403);
        abort_unless(in_array($channel, ['sms', 'whatsapp', 'email'], true), 422);
        $this->guardBranch($actor, $customer->branch);
        $connected = $channel === 'email' && ! in_array((string) config('mail.default'), ['log', 'array'], true);
        $note = CustomerNote::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'body' => $body,
            'channel' => $channel,
            'status' => $connected ? 'queued' : 'not_configured',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('customer.message_requested', $note, ['channel' => $channel, 'status' => $note->status], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
        ]);

        return $note;
    }

    public function assignRate(User $actor, Customer $customer, float $rate, ?string $unit): void
    {
        abort_unless($actor->hasPermission('customers.edit') && ! $actor->hasRole(RoleName::Accountant), 403);
        $this->guardBranch($actor, $customer->branch);
        RateAgreement::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'rate' => $rate,
            'unit' => $unit ?: 'kg',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('customer.rate_assigned', $customer, ['rate' => $rate], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
        ]);
    }

    public function remove(User $actor, Customer $customer, ?string $reason): void
    {
        abort_unless($actor->hasPermission('customers.delete'), 403);
        $this->guardBranch($actor, $customer->branch);
        $this->reasons->assert('delete', $reason);
        $customer->delete();
        $this->audit->record('customer.deleted', $customer, [], $customer->tenant_id, [
            'module' => 'customers',
            'branch_id' => $customer->branch_id,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    public function import(User $actor, Branch $branch, array $rows): int
    {
        abort_unless($this->canImport($actor), 403);
        $count = 0;
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['file' => 'Each row needs a name.']);
            }
            $this->create($actor, $branch, [
                'name' => $name,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'address' => $row['address'] ?? null,
                'tax_no' => $row['tax_no'] ?? null,
                'credit_limit' => $row['credit_limit'] ?? 0,
            ]);
            $count++;
        }
        $this->audit->record('customer.imported', null, ['rows' => $count], $branch->tenant_id, [
            'module' => 'customers',
            'branch_id' => $branch->id,
        ]);

        return $count;
    }
}
