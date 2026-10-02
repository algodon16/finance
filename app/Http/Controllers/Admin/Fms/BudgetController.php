<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\BudgetAllocation;
use App\Models\BudgetPlan;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Payment;
use App\Models\StudentAccount;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $q = BudgetPlan::with('items');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")->orWhere('department', 'ilike', "%{$s}%")->orWhere('budget_category', 'ilike', "%{$s}%"));
        }
        if ($request->filled('status')) $q->where('status', $request->status);
        $yearCol = BudgetPlan::yearColumn();
        if ($request->filled('academic_year')) $q->where($yearCol, $request->academic_year);
        $plans = $q->orderBy('created_at', 'desc')->paginate(12)->withQueryString();
        try {
            $years = BudgetPlan::select($yearCol)->distinct()->orderBy($yearCol)->pluck($yearCol);
        } catch (\Throwable $e) {
            $years = collect();
        }
        $counts = [
            'pending' => (int) BudgetPlan::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => (int) BudgetPlan::whereIn('status', ['approved', 'active'])->count(),
            'rejected' => (int) BudgetPlan::whereIn('status', ['rejected', 'for_revision', 'revision'])->count(),
            'all' => (int) BudgetPlan::count(),
            'total_proposed' => (float) BudgetPlan::sum('allocated_amount'),
        ];
        return view('admin.fms.budgets.index', compact('plans', 'counts', 'years'));
    }

    public function create()
    {
        return view('admin.fms.budgets.form', [
            'plan' => new BudgetPlan(),
            'departments' => \App\Models\Department::active()->orderBy('name')->pluck('name'),
            'academicYears' => \App\Models\AcademicYear::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:20',
            'department' => 'nullable|string|max:255',
            'budget_category' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive,closed,draft',
            'description' => 'nullable|string|max:5000',
        ]);
        // Admin-created official budgets must not duplicate an existing official plan.
        if (in_array($data['status'], BudgetPlan::OFFICIAL, true) && ! empty($data['department'])) {
            if ($dup = BudgetPlan::officialDuplicateExists($data['department'], $data['academic_year'])) {
                return back()->withErrors(['department' => 'Department "'.$data['department'].'" already has an official budget for '.$data['academic_year'].' (BUD-'.$dup->id.'). Review/approve that record instead of creating a duplicate.'])->withInput();
            }
        }
        $data['utilized_amount'] = 0;
        // Admin-created plans are direct official entries: mirror the amount
        // across requested/proposed/approved so the columns stay consistent.
        $data['requested_amount'] = $data['allocated_amount'];
        $data['proposed_amount'] = $data['allocated_amount'];
        if (in_array($data['status'], ['approved', 'active'], true)) {
            $data['approved_amount'] = $data['allocated_amount'];
        }
        $data['created_by'] = auth()->id();
        $plan = BudgetPlan::create($data);
        AuditService::log('create', 'budget_plans', (string) $plan->id, null, null, "Admin ".auth()->user()->name." created Budget Plan #{$plan->id} ({$plan->budget_name}).");
        return redirect()->route('admin.budgets.index')->with('success', 'Budget plan created.');
    }

    /** Hub filter options shared by admin budget pages. */
    protected function hubFilters(): array
    {
        $yearCol = BudgetPlan::yearColumn();
        try {
            $years = BudgetPlan::select($yearCol)->distinct()->orderBy($yearCol)->pluck($yearCol);
        } catch (\Throwable $e) {
            $years = collect();
        }
        return [
            'years' => $years,
            'departments' => BudgetPlan::whereNotNull('department')->where('department', '<>', '')->distinct()->orderBy('department')->pluck('department'),
        ];
    }

    protected function applyHubFilters($q, Request $request)
    {
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")
                ->orWhere('department', 'ilike', "%{$s}%")
                ->orWhere('budget_category', 'ilike', "%{$s}%")
                ->orWhere('funding_source', 'ilike', "%{$s}%"));
            if (ctype_digit($s)) $q->orWhere('id', (int) $s);
        }
        $yearCol = BudgetPlan::yearColumn();
        if ($request->filled('academic_year')) $q->where($yearCol, $request->academic_year);
        if ($request->filled('department')) $q->where('department', $request->department);
        return $q;
    }

    /** Admin workspace — same lifecycle records as the Accountant module. */
    public function overview(Request $request)
    {
        $data = BudgetPlan::workspace($request->input('search'), $request->input('status'), $request->input('academic_year'));
        $fundMap = Fund::where('status', 'active')->get()->keyBy('fund_name');

        // Get full deptRows from workspace for analytics
        $deptRows = $data['deptRows'];

        // Filter deptRows by search, status, academic_year, fund_source for pagination
        $filtered = collect($deptRows);
        if ($request->filled('search')) {
            $s = strtolower($request->search);
            $filtered = $filtered->filter(function ($d) use ($s) {
                return stripos($d['department'], $s) !== false || stripos($d['plan']->budget_name ?? '', $s) !== false;
            });
        }
        if ($request->filled('status')) {
            $filtered = $filtered->filter(function ($d) use ($request) {
                return $d['plan']->status === $request->status;
            });
        }
        if ($request->filled('academic_year')) {
            $filtered = $filtered->filter(function ($d) use ($request) {
                return ($d['plan']->academic_year ?? '') === $request->academic_year;
            });
        }
        if ($request->filled('fund_source')) {
            $filtered = $filtered->filter(function ($d) use ($request) {
                return ($d['plan']->funding_source ?? '') === $request->fund_source;
            });
        }

        // Paginate the filtered results
        $perPage = 10;
        $page = $request->input('page', 1);
        $total = $filtered->count();
        $paginatedDeptRows = $filtered->forPage($page, $perPage)->values();
        $paginatedDepts = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedDeptRows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $paginatedDepts->appends($request->query());

        // Use filtered for table, full for analytics
        return view('admin.fms.budgets.overview', array_merge($data, compact('fundMap', 'paginatedDeptRows', 'paginatedDepts')));
    }

    /** Admin History — complete per-request tracking record. */
    public function history(Request $request)
    {
        $q = $this->applyHubFilters(BudgetPlan::with(['creator', 'reviewer', 'approver'])->orderByDesc('created_at'), $request);
        if ($request->filled('status')) $q->where('status', $request->status);
        $plans = $q->paginate(15)->withQueryString();
        return view('admin.fms.budgets.history', array_merge($this->hubFilters(), compact('plans')));
    }

    /** Budget Requests inbox — requests forwarded by the accountant for admin approval. */
    public function requests(Request $request)
    {
        $yearCol = BudgetPlan::yearColumn();
        $q = BudgetPlan::with(['creator', 'reviewer'])->orderByDesc('created_at');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn($w) => $w->where('budget_name', 'ilike', "%{$s}%")
                ->orWhere('department', 'ilike', "%{$s}%")
                ->orWhere('budget_category', 'ilike', "%{$s}%")
                ->orWhere('funding_source', 'ilike', "%{$s}%"));
            if (ctype_digit($s)) $q->orWhere('id', (int) $s);
        }
        $tab = $request->input('tab', 'for_approval');
        if ($tab === 'for_approval') {
            $q->whereIn('status', ['for_approval', 'submitted', 'under_review']);
        } elseif ($tab !== 'all') {
            $q->where('status', $tab);
        }
        if ($request->filled('academic_year')) $q->where($yearCol, $request->academic_year);
        if ($request->filled('department')) $q->where('department', $request->department);
        $plans = $q->paginate(12)->withQueryString();
        $counts = [
            'for_approval' => (int) BudgetPlan::whereIn('status', ['for_approval', 'submitted', 'under_review'])->count(),
            'approved' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_APPROVED)->count(),
            'rejected' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_REJECTED)->count(),
            'returned' => (int) BudgetPlan::whereIn('status', BudgetPlan::REQUEST_RETURNED)->count(),
        ];
        $fundMap = Fund::where('status', 'active')->get()->keyBy('fund_name');
        try {
            $years = BudgetPlan::select($yearCol)->distinct()->orderBy($yearCol)->pluck($yearCol);
        } catch (\Throwable $e) {
            $years = collect();
        }
        $departments = BudgetPlan::whereNotNull('department')->where('department', '<>', '')->distinct()->orderBy('department')->pluck('department');
        return view('admin.fms.budgets.requests', compact('plans', 'counts', 'fundMap', 'years', 'departments', 'tab'));
    }

    /**
     * Approve a forwarded request: the approved amount becomes the official
     * budget and automatically flows into Budget Allocation + Fund Management.
     */
    public function approveRequest(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($budget->status, ['for_approval', 'submitted', 'under_review'], true), 422, 'Only forwarded requests can be approved.');
        $data = $request->validate([
            'admin_remarks' => 'nullable|string|max:5000',
            'approved_amount' => 'nullable|numeric|min:0.01|max:999999999999.99',
        ]);
        if ($dup = BudgetPlan::officialDuplicateExists($budget->department, $budget->academic_year, $budget->id)) {
            abort(422, 'Department "'.$budget->department.'" already has an official budget for '.$budget->academic_year.' (BUD-'.$dup->id.'). Resolve the duplicate first.');
        }
        $proposed = (float) ($budget->proposed_amount_value ?: $budget->requested_amount_value);
        // §15: optional explicit admin adjustment; defaults to the proposal.
        // Never silently overwrites the proposal — reason recorded + audited.
        $approved = isset($data['approved_amount']) ? (float) $data['approved_amount'] : $proposed;
        $adjusted = abs($approved - $proposed) > 0.009;
        if ($adjusted && empty(trim((string) ($data['admin_remarks'] ?? '')))) {
            return back()->withErrors(['admin_remarks' => 'An adjustment reason is required when changing the approved amount.'])->withInput();
        }
        abort_if($approved <= 0, 422, 'Approved amount must be greater than 0.');
        // Fund guard: block approval when the linked fund cannot cover it.
        $fund = $budget->funding_source ? Fund::where('fund_name', $budget->funding_source)->where('status', 'active')->lockForUpdate()->first() : null;
        if ($fund && (float) $fund->available_amount < $approved) {
            return back()->withErrors(['amount' => 'Insufficient available fund for this allocation. Available: P'.number_format((float) $fund->available_amount, 2).'.']);
        }
        $old = $budget->toArray();
        DB::transaction(function () use ($budget, $approved, $fund, $request) {
            // Data separation: requested/proposed stay as recorded;
            // approval writes approved_amount + allocated_amount only.
            $budget->update([
                'approved_amount' => $approved,
                'allocated_amount' => $approved,
                'status' => 'approved',
                'reviewed_at' => now(), 'reviewed_by' => $budget->reviewed_by ?: auth()->id(),
                'approved_by' => auth()->id(), 'approved_at' => now(),
                'admin_remarks' => $request->input('admin_remarks') ?: $budget->admin_remarks,
            ]);
            // Auto-create the Budget Allocation record — no manual re-entry.
            BudgetAllocation::create([
                'budget_plan_id' => $budget->id,
                'allocation_type' => 'department',
                'allocated_to' => $budget->department,
                'amount' => $approved,
                'allocation_date' => now()->toDateString(),
                'remarks' => 'Auto-created from approved request '.$budget->request_id.'.',
                'created_by' => auth()->id(),
            ]);
            // Auto-update Fund Management.
            if ($fund) {
                $fund->decrement('current_balance', $approved);
                FundTransaction::create([
                    'fund_id' => $fund->id,
                    'transaction_type' => 'outflow',
                    'amount' => $approved,
                    'transaction_date' => now()->toDateString(),
                    'reference_number' => $budget->request_id,
                    'description' => 'Budget approved for '.$budget->department.' ('.$budget->request_id.').',
                    'created_by' => auth()->id(),
                ]);
            }
        });
        AuditService::log('approve', 'budget_plans', (string) $budget->id, ['status' => $old['status'], 'proposed_amount' => $proposed], ['status' => 'approved', 'approved_amount' => $approved, 'allocated_amount' => $approved], "Admin ".auth()->user()->name." approved budget request {$budget->request_id} ({$budget->budget_name}) at P".number_format($approved, 2).($adjusted ? " (adjusted from proposed P".number_format($proposed, 2).": ".($data['admin_remarks'] ?? '').")" : "")." Allocation auto-created.");
        WorkflowService::notifyUser($budget->created_by, "Budget request {$budget->request_id} approved at P".number_format($approved, 2).".", "Request '{$budget->budget_name}' was approved and automatically allocated.");
        return back()->with('success', 'Request approved. Allocation auto-created and fund updated.');
    }

    /** Reject a forwarded request (reason required). */
    public function rejectRequest(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($budget->status, ['for_approval', 'submitted', 'under_review'], true), 422, 'Only forwarded requests can be rejected.');
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'rejected', 'rejection_reason' => $data['rejection_reason'],
            'admin_remarks' => $data['admin_remarks'] ?? $budget->admin_remarks,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => null, 'approved_at' => null,
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('reject', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected budget request {$budget->request_id}: {$data['rejection_reason']}");
        WorkflowService::notifyUser($budget->created_by, "Budget request {$budget->request_id} was rejected.", "Reason: {$data['rejection_reason']}");
        return back()->with('success', 'Request rejected.');
    }

    /** Return a forwarded request for revision (remarks required). */
    public function returnRequest(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can return requests.');
        $data = $request->validate(['admin_remarks' => 'required|string|max:5000']);
        abort_if(! in_array($budget->status, ['for_approval', 'submitted', 'under_review'], true), 422, 'Only forwarded requests can be returned.');
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'for_revision',
            'admin_remarks' => $data['admin_remarks'],
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => null, 'approved_at' => null,
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('revise', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'for_revision'], "Admin ".auth()->user()->name." returned budget request {$budget->request_id} for revision: {$data['admin_remarks']}");
        WorkflowService::notifyUser($budget->created_by, "Budget request {$budget->request_id} returned for revision.", "Admin comment: {$data['admin_remarks']}");
        return back()->with('success', 'Request returned for revision.');
    }

    public function show(BudgetPlan $budget)
    {
        $budget->load(['items', 'allocations', 'creator', 'approver', 'submitter', 'reviewer']);
        $allocated = (float) $budget->allocations()->sum('amount');
        $history = \App\Models\AuditLog::with('user')->where('module', 'budget_plans')->where('record_id', (string) $budget->id)->latest('id')->take(30)->get();
        return view('admin.fms.budgets.show', compact('budget', 'allocated', 'history'));
    }

    public function edit(BudgetPlan $budget)
    {
        return view('admin.fms.budgets.form', [
            'plan' => $budget,
            'departments' => \App\Models\Department::active()->orderBy('name')->pluck('name'),
            'academicYears' => \App\Models\AcademicYear::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, BudgetPlan $budget)
    {
        $data = $request->validate([
            'budget_name' => 'required|string|max:255',
            'academic_year' => 'required|string|max:20',
            'department' => 'nullable|string|max:255',
            'budget_category' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive,closed,draft',
            'description' => 'nullable|string|max:5000',
        ]);
        // The approved amount is official — it cannot be rewritten through edit once official.
        if ($budget->isOfficial() && bccomp((string) $data['allocated_amount'], (string) $budget->allocated_amount, 2) !== 0) {
            abort(422, 'The approved amount of an official budget cannot be changed directly. Adjust through allocations or return the plan for revision.');
        }
        // Flipping a plan to official must not create a duplicate either.
        $resultStatus = $data['status'];
        if (in_array($resultStatus, BudgetPlan::OFFICIAL, true) && ! empty($data['department'])) {
            if ($dup = BudgetPlan::officialDuplicateExists($data['department'], $data['academic_year'], $budget->id)) {
                return back()->withErrors(['department' => 'Department "'.$data['department'].'" already has an official budget for '.$data['academic_year'].' (BUD-'.$dup->id.').'])->withInput();
            }
        }
        $old = $budget->toArray();
        $budget->update($data);
        AuditService::log('update', 'budget_plans', (string) $budget->id, $old, $budget->fresh()->toArray(), "Admin ".auth()->user()->name." updated Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.show', $budget)->with('success', 'Budget plan updated.');
    }

    public function destroy(BudgetPlan $budget)
    {
        // No hard deletes on financial records — archive by status instead.
        abort_if($budget->isOfficial(), 422, 'Official budgets are part of the books and cannot be removed. Cancel or revise instead.');
        abort_if((float) $budget->utilized_amount > 0 || $budget->allocations()->exists(), 422, 'Plans with allocations or utilization cannot be removed.');
        $old = $budget->toArray();
        $budget->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        AuditService::log('delete', 'budget_plans', (string) $budget->id, $old, $budget->fresh()->toArray(), "Admin ".auth()->user()->name." archived Budget Plan #{$budget->id} (status → cancelled; record preserved).");
        return redirect()->route('admin.budgets.index')->with('success', 'Budget plan archived (record preserved).');
    }

    /** Approve a submitted budget plan — same record, submitted → approved. */
    public function approve(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can approve.');
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted plans can be approved.');
        $request->validate(['admin_remarks' => 'nullable|string|max:5000']);
        // Approving must not crown a second official budget for the same department + year.
        if ($dup = BudgetPlan::officialDuplicateExists($budget->department, $budget->academic_year, $budget->id)) {
            abort(422, 'Department "'.$budget->department.'" already has an official budget for '.$budget->academic_year.' (BUD-'.$dup->id.'). Resolve the duplicate first.');
        }
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => auth()->id(), 'approved_at' => now(),
            'admin_remarks' => $request->input('admin_remarks') ?: $budget->admin_remarks,
        ]));
        AuditService::log('approve', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'approved'], "Admin ".auth()->user()->name." approved Budget Plan BUD-{$budget->id} ({$budget->budget_name}) — now in Budget Planning Overview as approved.");
        \App\Services\WorkflowService::notifyUser($budget->created_by, "Budget Plan BUD-{$budget->id} has been approved.", "Budget Plan '{$budget->budget_name}' was approved and is now available in the overview.");
        return back()->with('success', 'Budget plan approved. It is now visible as approved in both Admin and Accountant modules.');
    }

    /** Reject a submitted budget plan — same record, submitted → rejected (reason required). */
    public function reject(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can reject.');
        $data = $request->validate(['rejection_reason' => 'required|string|max:5000', 'admin_remarks' => 'nullable|string|max:5000']);
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted plans can be rejected.');
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'rejected', 'rejection_reason' => $data['rejection_reason'],
            'admin_remarks' => $data['admin_remarks'] ?? $budget->admin_remarks,
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => null, 'approved_at' => null,
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('reject', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'rejected'], "Admin ".auth()->user()->name." rejected Budget Plan BUD-{$budget->id}: {$data['rejection_reason']}");
        \App\Services\WorkflowService::notifyUser($budget->created_by, "Budget Plan BUD-{$budget->id} was rejected. Reason: {$data['rejection_reason']}", "Revise the same record and resubmit from Budget Planning.");
        return back()->with('success', 'Budget plan rejected and returned to Accountant for revision.');
    }

    /** Return for revision — same record, submitted → for_revision (admin comment required). */
    public function forRevision(Request $request, BudgetPlan $budget)
    {
        abort_unless(auth()->user()->role === 'admin', 403, 'Only admin can return plans.');
        $data = $request->validate(['admin_remarks' => 'required|string|max:5000']);
        abort_if(! in_array($budget->status, ['submitted', 'under_review'], true), 422, 'Only submitted plans can be returned for revision.');
        $old = $budget->toArray();
        DB::transaction(fn() => $budget->update([
            'status' => 'for_revision',
            'admin_remarks' => $data['admin_remarks'],
            'reviewed_at' => now(), 'reviewed_by' => auth()->id(),
            'approved_by' => null, 'approved_at' => null,
            'revision_number' => ((int) $budget->revision_number) + 1,
        ]));
        AuditService::log('revise', 'budget_plans', (string) $budget->id, ['status' => $old['status']], ['status' => 'for_revision'], "Admin ".auth()->user()->name." returned Budget Plan BUD-{$budget->id} for revision: {$data['admin_remarks']}");
        \App\Services\WorkflowService::notifyUser($budget->created_by, "Budget Plan BUD-{$budget->id} was returned for revision.", "Admin comment: {$data['admin_remarks']}");
        return back()->with('success', 'Budget plan returned to Accountant for revision.');
    }

    public function allocate(Request $request, BudgetPlan $budget)
    {
        $data = $request->validate([
            'allocation_type' => 'required|in:department,program,scholarship,operational,academic',
            'allocated_to' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'allocation_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
        ]);
        // Server-side guard: allocation cannot exceed remaining.
        $remaining = (float) $budget->remaining_amount;
        if ((float) $data['amount'] > $remaining) {
            return back()->withErrors(['amount' => 'Allocation exceeds remaining budget of P'.number_format($remaining, 2).'.'])->withInput();
        }
        DB::transaction(function () use ($data, $budget) {
            BudgetAllocation::create($data + ['budget_plan_id' => $budget->id, 'created_by' => auth()->id()]);
            $budget->increment('utilized_amount', $data['amount']);
        });
        AuditService::log('allocate', 'budget_allocations', (string) $budget->id, null, null, "Admin ".auth()->user()->name." allocated P".number_format($data['amount'], 2)." from Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.show', $budget)->with('success', 'Funds allocated.');
    }

    public function destroyAllocation(BudgetPlan $budget, BudgetAllocation $allocation)
    {
        DB::transaction(function () use ($budget, $allocation) {
            $budget->decrement('utilized_amount', $allocation->amount);
            $allocation->delete();
        });
        AuditService::log('delete', 'budget_allocations', (string) $allocation->id, null, null, "Admin ".auth()->user()->name." removed allocation #{$allocation->id} from Budget Plan #{$budget->id}.");
        return redirect()->route('admin.budgets.show', $budget)->with('success', 'Allocation removed.');
    }

    /**
     * AI-assisted budget planning form (GET — no validation, display only).
     */
    public function ai()
    {
        return view('admin.fms.budgets.ai');
    }

    /**
     * AI-assisted budget planning (rule-based forecasting engine).
     * Recommendations are labeled and never auto-applied.
     */
    public function aiRecommend(Request $request)
    {
        $data = $request->validate([
            'declared_allowance' => 'required|numeric|min:0|max:999999999.99',
            'payment_frequency' => 'required|in:monthly,quarterly,semestral,annual,one-time',
            'outstanding_balance' => 'required|numeric|min:0|max:999999999.99',
            'total_assessment' => 'nullable|numeric|min:0|max:999999999.99',
            'payment_deadline' => 'required|date|after:today',
            'available_budget' => 'nullable|numeric|min:0|max:999999999.99',
        ]);

        $balance = (float) $data['outstanding_balance'];
        $deadline = new \DateTime($data['payment_deadline']);
        $now = new \DateTime('today');
        $monthsLeft = max(1, ($deadline->format('Y') - $now->format('Y')) * 12 + ($deadline->format('n') - $now->format('n')));

        $divisor = match ($data['payment_frequency']) {
            'monthly' => $monthsLeft,
            'quarterly' => max(1, (int) ceil($monthsLeft / 3)),
            'semestral' => max(1, (int) ceil($monthsLeft / 6)),
            'annual' => max(1, (int) ceil($monthsLeft / 12)),
            default => 1,
        };

        $suggestedInstallment = round($balance / $divisor, 2);
        $allowance = (float) $data['declared_allowance'];
        $affordable = $allowance > 0 ? $suggestedInstallment <= $allowance : true;

        // Build schedule.
        $schedule = [];
        $running = $balance;
        $cursor = clone $now;
        for ($i = 1; $i <= $divisor && $running > 0.009; $i++) {
            $cursor->modify('+1 month');
            $pay = min($suggestedInstallment, $running);
            $running = round($running - $pay, 2);
            $schedule[] = ['installment' => $i, 'due' => $cursor->format('Y-m-d'), 'amount' => $pay, 'projected_remaining' => max(0, $running)];
        }

        $recommendation = [
            'suggested_installment' => $suggestedInstallment,
            'num_installments' => $divisor,
            'months_to_deadline' => $monthsLeft,
            'projected_remaining_after_plan' => end($schedule)['projected_remaining'] ?? $balance,
            'completion_projection' => $cursor->format('Y-m-d'),
            'affordable' => $affordable,
            'allocation_forecast' => $affordable
                ? 'Declared allowance covers the suggested installment schedule.'
                : 'Declared allowance is below the suggested installment. Consider extending the schedule or reducing the balance with a partial payment.',
            'schedule' => $schedule,
        ];

        // Cross-reference real institutional aggregates (read-only context).
        $context = [
            'avg_student_balance' => round((float) (StudentAccount::where('outstanding_balance', '>', 0)->avg('outstanding_balance') ?? 0), 2),
            'recent_avg_payment' => round((float) (Payment::where('status', 'approved')->avg('amount') ?? 0), 2),
        ];

        return view('admin.fms.budgets.ai', compact('recommendation', 'context', 'data'));
    }
}
