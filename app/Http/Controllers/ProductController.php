<?php

namespace App\Http\Controllers;

use App\Models\Csms\Commodity;
use App\Models\Csms\CommodityGrade;
use App\Models\Csms\CommodityShrinkageChange;
use App\Models\Csms\CommodityVariety;
use App\Models\Tenant;
use App\Services\Products\ProductMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductMaster $products) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('products.view'), 403);
        $search = (string) $request->query('q', '');
        $commodities = Commodity::query()->visibleTo($request->user())
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(50)
            ->get();

        return view('products.index', [
            'commodities' => $commodities,
            'search' => $search,
            'products' => $this->products,
            'tenants' => $request->user()->isSuperAdmin() ? Tenant::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function store(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'variety' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:255'],
        ]);
        $this->products->create($request->user(), $data, $tenant);

        return back()->with('status', 'Product saved.');
    }

    public function show(Request $request, Commodity $commodity): View
    {
        abort_unless($request->user()->hasPermission('products.view'), 403);
        $commodity->load(['varieties.grades', 'units', 'incompatibilities.other', 'shrinkageChanges' => fn ($query) => $query->latest()]);

        return view('products.show', [
            'commodity' => $commodity,
            'products' => $this->products,
            'others' => Commodity::query()->whereKeyNot($commodity->id)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['nullable', 'string', 'max:64']]);
        $this->products->update($request->user(), $commodity, $data);

        return back()->with('status', 'Product updated.');
    }

    public function storeVariety(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:64']]);
        $this->products->addVariety($request->user(), $commodity, $data['name'], $data['code']);

        return back()->with('status', 'Variety saved.');
    }

    public function updateVariety(Request $request, CommodityVariety $commodity_variety): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $this->products->updateVariety($request->user(), $commodity_variety, $data['name']);

        return back()->with('status', 'Variety updated.');
    }

    public function storeGrade(Request $request, CommodityVariety $commodity_variety): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:64']]);
        $this->products->addGrade($request->user(), $commodity_variety, $data['name'], $data['code']);

        return back()->with('status', 'Grade saved.');
    }

    public function updateGrade(Request $request, CommodityGrade $commodity_grade): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $this->products->updateGrade($request->user(), $commodity_grade, $data['name']);

        return back()->with('status', 'Grade updated.');
    }

    public function storage(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate([
            'storage_temperature' => ['nullable', 'numeric'],
            'storage_humidity' => ['nullable', 'numeric'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->products->storeConditions($request->user(), $commodity, $data);

        return back()->with('status', 'Storage conditions saved.');
    }

    public function unit(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate([
            'unit' => ['required', 'in:bag,kg,pallet'],
            'factor_to_kg' => ['required', 'numeric', 'min:0.001'],
        ]);
        $this->products->saveUnit($request->user(), $commodity, $data['unit'], (float) $data['factor_to_kg']);

        return back()->with('status', 'Unit saved.');
    }

    public function incompatible(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate(['incompatible_commodity_id' => ['required', 'integer']]);
        $other = Commodity::query()->findOrFail($data['incompatible_commodity_id']);
        $this->products->addIncompatible($request->user(), $commodity, $other);

        return back()->with('status', 'Incompatible product saved.');
    }

    public function shrinkage(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate(['percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $this->products->proposeShrinkage($request->user(), $commodity, (float) $data['percent']);

        return back()->with('status', 'Shrinkage submitted.');
    }

    public function decideShrinkage(Request $request, CommodityShrinkageChange $commodity_shrinkage_change): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->products->decideShrinkage($request->user(), $commodity_shrinkage_change, $request->boolean('approve'));

        return back()->with('status', 'Shrinkage decision saved.');
    }

    public function deactivate(Request $request, Commodity $commodity): RedirectResponse
    {
        $this->products->deactivate($request->user(), $commodity);

        return back()->with('status', 'Product deactivated.');
    }

    public function destroy(Request $request, Commodity $commodity): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->products->remove($request->user(), $commodity, $data['reason'] ?? null);

        return redirect()->route('commodities.index')->with('status', 'Product removed.');
    }
}
