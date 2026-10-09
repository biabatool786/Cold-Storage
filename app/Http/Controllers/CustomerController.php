<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\CreditLimitChange;
use App\Models\Csms\Customer;
use App\Models\Csms\CustomerDocument;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\CustomerMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerMaster $customers) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('customers.view'), 403);
        $user = $request->user();
        $search = trim($request->string('q')->toString());
        $status = trim($request->string('status')->toString());
        $records = Customer::query()->visibleTo($user)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        return view('customers.index', [
            'customers' => $records,
            'search' => $search,
            'status' => $status,
            'master' => $this->customers,
            'branches' => $this->branches($user),
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->customers->create($request->user(), $branch, $data);

        return back()->with('status', 'Customer submitted for approval.');
    }

    public function show(Customer $customer): View
    {
        abort_unless(request()->user()?->hasPermission('customers.view'), 403);
        $this->customers->guardBranch(request()->user(), $customer->branch);
        $customer->load('documents', 'notes', 'rateAgreements', 'creditChanges');

        return view('customers.show', [
            'customer' => $customer,
            'master' => $this->customers,
            'activity' => AuditLog::query()->where('subject_type', $customer->getMorphClass())->where('subject_id', $customer->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:50'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->customers->update($request->user(), $customer, $data, $data['reason'] ?? null);

        return back()->with('status', 'Customer saved.');
    }

    public function approve(Request $request, Customer $customer): RedirectResponse
    {
        $this->customers->approve($request->user(), $customer);

        return back()->with('status', 'Customer approved.');
    }

    public function credit(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->customers->setCredit($request->user(), $customer, (float) $data['credit_limit'], $data['reason'] ?? null);

        return back()->with('status', 'Credit limit recorded.');
    }

    public function approveCredit(Request $request, CreditLimitChange $creditLimitChange): RedirectResponse
    {
        $this->customers->approveCredit($request->user(), $creditLimitChange);

        return back()->with('status', 'Credit limit approved.');
    }

    public function hold(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:hold,blacklisted'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->customers->hold($request->user(), $customer, $data['status'], $data['reason']);

        return back()->with('status', 'Customer status updated.');
    }

    public function release(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->customers->release($request->user(), $customer, $data['reason']);

        return back()->with('status', 'Hold removed.');
    }

    public function document(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['document' => ['required', 'file', 'max:5120']]);
        $this->customers->storeDocument($request->user(), $customer, $data['document']);

        return back()->with('status', 'KYC document stored.');
    }

    public function downloadDocument(CustomerDocument $customerDocument)
    {
        $path = $this->customers->viewDocument(request()->user(), $customerDocument);

        return response()->file($path, ['Content-Disposition' => 'inline; filename="'.$customerDocument->original_name.'"']);
    }

    public function note(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $this->customers->note($request->user(), $customer, $data['body']);

        return back()->with('status', 'Note saved.');
    }

    public function message(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,whatsapp,email'],
            'body' => ['required', 'string', 'max:1000'],
        ]);
        $note = $this->customers->message($request->user(), $customer, $data['channel'], $data['body']);
        $text = $note->status === 'not_configured'
            ? 'No SMS, WhatsApp, or email provider is connected. The request was recorded and was not sent.'
            : 'Message queued.';

        return back()->with('status', $text);
    }

    public function rate(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);
        $this->customers->assignRate($request->user(), $customer, (float) $data['rate'], $data['unit'] ?? null);

        return back()->with('status', 'Rate card saved.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->customers->remove($request->user(), $customer, $data['reason'] ?? null);

        return redirect()->route('customers.index')->with('status', 'Customer removed.');
    }

    public function import(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx']]);
        $rows = $this->rows($data['file']->getRealPath(), $data['file']->getClientOriginalExtension());
        $count = $this->customers->import($request->user(), $branch, $rows);

        return back()->with('status', $count.' customers imported.');
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('customers.print'), 403);
        $audit->record('customer.exported', null, [], $request->user()->tenant_id, ['module' => 'customers']);
        $user = $request->user();

        return response()->streamDownload(function () use ($user): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Email', 'City', 'Status', 'Credit limit']);
            Customer::query()->visibleTo($user)->orderBy('name')->each(function (Customer $customer) use ($out, $user): void {
                fputcsv($out, [
                    $customer->name,
                    $customer->phone,
                    $customer->email,
                    $customer->city,
                    $customer->status,
                    $this->customers->seesFinancials($user) ? $customer->credit_limit : '',
                ]);
            });
            fclose($out);
        }, 'customers.csv', ['Content-Type' => 'text/csv']);
    }

    private function branches($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(\App\Enums\RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(string $path, string $extension): array
    {
        if (strtolower($extension) !== 'xlsx') {
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle) ?: [];
            $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
            $rows = [];
            while (($line = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($header, array_pad($line, count($header), ''));
            }
            fclose($handle);

            return $rows;
        }

        $zip = new \ZipArchive();
        $zip->open($path);
        $shared = [];
        if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
            $sheet = simplexml_load_string($xml);
            foreach ($sheet->si as $item) {
                $shared[] = (string) $item->t;
            }
        }
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $parsed = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $values[] = $value;
            }
            $parsed[] = $values;
        }
        $zip->close();
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $parsed[0] ?? []);
        $rows = [];
        foreach (array_slice($parsed, 1) as $line) {
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }

        return $rows;
    }
}
