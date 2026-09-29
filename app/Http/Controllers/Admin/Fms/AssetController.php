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
        if ($request->filled('approval_status')) $q->where('approval_status', $request->approval_status);
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
        // Admin-created assets are finalized directly (Admin is the final authority).
        $data['approval_status'] = 'approved';
        $data['approved_by'] = auth()->id();
        $data['approved_at'] = now();
        $record = Asset::create($data);
        AuditService::log('create', 'assets', (string) $record->id, null, null, "Admin ".auth()->user()->name." registered Asset #{$record->asset_code} ({$record->asset_name}).");
        return redirect()->route('admin.assets.index')->with('success', 'Asset registered.');
    }

    public function show(Asset $asset)
    {
        $history = \App\Models\AuditLog::with('user')->where('module', 'assets')->where('record_id', (string) $asset->id)->latest('id')->take(30)->get();
        return view('admin.fms.assets.show', ['record' => $asset, 'history' => $history]);
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

    /** Approve an accountant-submitted asset — same record, submitted → approved. */
    public function approve(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($asset->approval_status ?? 'draft', ['submitted', 'under_review'], true), 422, 'Only submitted assets can be approved.');
        $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        $asset->update([
            'approval_status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => auth()->id(), 'approved_at' => now(),
            'admin_remarks' => $request->input('admin_remarks') ?: $asset->admin_remarks,
        ]);
        AuditService::log('approve', 'assets', (string) $asset->id, ['status' => 'submitted'], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Asset {$asset->asset_code} — now an official system asset.");
        \App\Services\WorkflowService::notifyUser($asset->created_by, "Asset {$asset->asset_code} has been approved.", "It is now an official system asset; depreciation uses this record.");
        return back()->with('success', 'Asset approved. Same record is now official in both modules.');
    }

    /** Reject — same record, submitted → rejected (reason required). */
    public function reject(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($asset->approval_status ?? 'draft', ['submitted', 'under_review'], true), 422, 'Only submitted assets can be rejected.');
        $asset->update([
            'approval_status' => 'rejected', 'rejection_reason' => $data['rejection_reason'],
            'admin_remarks' => $data['admin_remarks'] ?? $asset->admin_remarks,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'revision_number' => ((int) $asset->revision_number) + 1,
        ]);
        AuditService::log('reject', 'assets', (string) $asset->id, ['status' => 'submitted'], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Asset {$asset->asset_code}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($asset->created_by, "Asset {$asset->asset_code} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit.");
        return back()->with('success', 'Asset rejected and returned for revision.');
    }
}
