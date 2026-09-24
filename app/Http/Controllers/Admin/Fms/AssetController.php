<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $q = Asset::query();
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('asset_name', 'ilike', "%{$s}%")->orWhere('asset_code', 'ilike', "%{$s}%")->orWhere('serial_number', 'ilike', "%{$s}%"));
        }
        if ($request->filled('asset_status')) $q->where('asset_status', $request->asset_status);
        if ($request->filled('asset_category')) $q->where('asset_category', $request->asset_category);
        $records = $q->orderBy('asset_name')->paginate(15)->withQueryString();

        $active = Asset::where('asset_status', 'active')->get();
        $summary = [
            'count' => Asset::count(),
            'acquisition' => (float) Asset::sum('acquisition_cost'),
            'accumulated' => round($active->sum(fn($a) => (float) $a->accumulated_depreciation), 2),
            'book' => round($active->sum(fn($a) => (float) $a->book_value), 2),
        ];
        return view('admin.fms.assets.index', compact('records', 'summary'));
    }

    public function create()
    {
        return view('admin.fms.assets.form', ['record' => new Asset()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_name' => 'required|string|max:255',
            'asset_category' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'acquisition_date' => 'required|date|before_or_equal:today',
            'acquisition_cost' => 'required|numeric|min:0.01|max:999999999999.99',
            'salvage_value' => 'required|numeric|min:0|lt:acquisition_cost',
            'useful_life_years' => 'required|integer|min:1|max:100',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'custodian' => 'nullable|string|max:255',
            'asset_status' => 'required|in:active,maintenance,disposed,lost,retired',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $data['asset_code'] = 'AST-'.now()->format('Ymd').'-'.strtoupper(uniqid());
        $data['created_by'] = auth()->id();
        $record = Asset::create($data);
        AuditService::log('create', 'assets', (string) $record->id, null, null, "Admin ".auth()->user()->name." registered Asset #{$record->asset_code} ({$record->asset_name}).");
        return redirect()->route('admin.assets.index')->with('success', 'Asset registered.');
    }

    public function show(Asset $asset)
    {
        return view('admin.fms.assets.show', ['record' => $asset]);
    }

    public function edit(Asset $asset)
    {
        return view('admin.fms.assets.form', ['record' => $asset]);
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'asset_name' => 'required|string|max:255',
            'asset_category' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'acquisition_date' => 'required|date|before_or_equal:today',
            'acquisition_cost' => 'required|numeric|min:0.01|max:999999999999.99',
            'salvage_value' => 'required|numeric|min:0|lt:acquisition_cost',
            'useful_life_years' => 'required|integer|min:1|max:100',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'custodian' => 'nullable|string|max:255',
            'asset_status' => 'required|in:active,maintenance,disposed,lost,retired',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $old = $asset->toArray();
        $asset->update($data);
        AuditService::log('update', 'assets', (string) $asset->id, $old, $asset->fresh()->toArray(), "Admin ".auth()->user()->name." updated Asset #{$asset->asset_code}.");
        return redirect()->route('admin.assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        $old = $asset->toArray();
        $asset->delete();
        AuditService::log('delete', 'assets', (string) $asset->id, $old, null, "Admin ".auth()->user()->name." deleted Asset #{$old['asset_code']}.");
        return redirect()->route('admin.assets.index')->with('success', 'Asset deleted.');
    }
}
