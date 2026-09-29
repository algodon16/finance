<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Accountant → Asset and Depreciation Management (preparation side).
 * Same `assets` table as Admin → Asset and Depreciation Management (review side).
 * approval_status workflow is separate from asset_status (physical state).
 */
class AssetController extends Controller
{
    public function index(Request $request)
    {
        $q = Asset::orderByDesc('created_at');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('asset_name', 'ilike', "%{$s}%")->orWhere('asset_code', 'ilike', "%{$s}%"));
        }
        if ($request->filled('approval_status')) $q->where('approval_status', $request->approval_status);
        $records = $q->paginate(12)->withQueryString();
        $summary = [
            'draft' => (int) Asset::where('approval_status', 'draft')->count(),
            'pending' => (int) Asset::whereIn('approval_status', ['submitted', 'under_review'])->count(),
            'approved' => (int) Asset::where('approval_status', 'approved')->count(),
            'book' => round(Asset::where('approval_status', 'approved')->get()->sum(fn($a) => (float) $a->book_value), 2),
        ];
        return view('accountant.assets.index', compact('records', 'summary'));
    }

    public function create()
    {
        return view('accountant.assets.form', ['record' => new Asset()]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        $data = $this->validateData($request);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('asset-docs', 'public');
        }
        $data['asset_code'] = 'AST-'.now()->format('Ymd').'-'.strtoupper(uniqid());
        $data['asset_status'] = 'active';
        $data['approval_status'] = 'draft';
        $data['created_by'] = auth()->id();
        $record = Asset::create($data);
        AuditService::log('create', 'assets', (string) $record->id, null, null, "Accountant ".auth()->user()->name." prepared Asset {$record->asset_code} ({$record->asset_name}).");
        if ($request->input('action') === 'submit') {
            WorkflowService::submit($record->fresh(), 'assets', 'Asset', 'approval_status');
            return redirect()->route('accountant.assets.index')->with('success', 'Asset submitted. Same record is now pending in Admin → Asset and Depreciation Management.');
        }
        return redirect()->route('accountant.assets.index')->with('success', 'Asset saved as draft.');
    }

    public function show(Asset $asset)
    {
        $history = AuditLog::where('module', 'assets')->where('record_id', (string) $asset->id)->latest('id')->take(20)->get();
        return view('accountant.assets.show', ['record' => $asset, 'history' => $history]);
    }

    public function edit(Asset $asset)
    {
        abort_if(! in_array($asset->approval_status ?? 'draft', ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Only draft or rejected/revision assets can be edited.');
        return view('accountant.assets.form', ['record' => $asset]);
    }

    public function update(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        abort_if(! in_array($asset->approval_status ?? 'draft', ['draft', 'for_revision', 'revision', 'rejected', 'cancelled'], true), 422, 'Approved/submitted assets cannot be edited directly.');
        $data = $this->validateData($request);
        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')->store('asset-docs', 'public');
        }
        $old = $asset->approval_status;
        DB::transaction(fn() => $asset->update($data + [
            'approval_status' => 'draft', 'rejection_reason' => null,
            'revision_number' => ((int) $asset->revision_number) + 1,
        ]));
        AuditService::log('update', 'assets', (string) $asset->id, ['status' => $old], ['status' => 'draft'], "Accountant ".auth()->user()->name." revised Asset {$asset->asset_code}. Same record, no duplicate.");
        return redirect()->route('accountant.assets.show', $asset)->with('success', 'Asset revised and saved as draft.');
    }

    public function submit(Asset $asset)
    {
        abort_unless(auth()->user()->role === 'accountant', 403, 'Accountant cannot approve own submission.');
        WorkflowService::submit($asset, 'assets', 'Asset', 'approval_status');
        return back()->with('success', 'Asset submitted for admin review.');
    }

    public function cancel(Asset $asset)
    {
        abort_unless(auth()->user()->role === 'accountant', 403);
        WorkflowService::cancelSubmission($asset, 'assets', 'Asset', 'approval_status');
        return back()->with('success', 'Submission withdrawn to draft.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
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
            'remarks' => 'nullable|string|max:2000',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
    }
}
