<?php

namespace App\Http\Controllers\Admin\Fms;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundController extends Controller
{
    public function index(Request $request)
    {
        $q = Fund::query();
        if ($request->filled('search')) $q->where('fund_name', 'ilike', '%'.$request->search.'%');
        if ($request->filled('fund_type')) $q->where('fund_type', $request->fund_type);
        if ($request->filled('status')) $q->where('status', $request->status);
        $funds = $q->orderBy('fund_name')->paginate(15)->withQueryString();

        $totals = [
            'initial' => (float) Fund::sum('initial_balance'),
            'current' => (float) Fund::sum('current_balance'),
            'reserved' => (float) Fund::sum('reserved_amount'),
            'available' => (float) Fund::where('status', 'active')
                ->selectRaw('COALESCE(SUM(current_balance - reserved_amount), 0) as total')
                ->value('total'),
        ];

        return view('admin.fms.funds.index', compact('funds', 'totals'));
    }

    public function create()
    {
        return view('admin.fms.funds.form', ['fund' => new Fund()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fund_name' => 'required|string|max:255|unique:funds,fund_name',
            'fund_source' => 'nullable|string|max:255',
            'fund_type' => 'required|in:general,academic,scholarship,department,campus,emergency,special',
            'initial_balance' => 'required|numeric|min:0|max:999999999999.99',
            'description' => 'nullable|string|max:5000',
            'status' => 'required|in:active,inactive',
        ]);
        $data['current_balance'] = $data['initial_balance'];
        $data['reserved_amount'] = 0;
        $data['created_by'] = auth()->id();
        $fund = DB::transaction(function () use ($data) {
            $f = Fund::create($data);
            FundTransaction::create([
                'fund_id' => $f->id, 'transaction_type' => 'inflow',
                'amount' => $f->initial_balance, 'transaction_date' => today(),
                'reference_number' => 'OPENING-'.$f->id,
                'description' => 'Opening balance', 'created_by' => auth()->id(),
            ]);
            return $f;
        });
        AuditService::log('create', 'funds', (string) $fund->id, null, null, "Admin ".auth()->user()->name." created Fund #{$fund->fund_name}.");
        return redirect()->route('admin.funds.index')->with('success', 'Fund created.');
    }

    public function show(Fund $fund)
    {
        $fund->load(['transactions' => fn($q) => $q->latest()]);
        $totals = [
            'initial' => (float) $fund->initial_balance,
            'current' => (float) $fund->current_balance,
            'reserved' => (float) $fund->reserved_amount,
            'available' => (float) $fund->available_amount,
        ];
        return view('admin.fms.funds.show', compact('fund', 'totals'));
    }

    public function edit(Fund $fund)
    {
        return view('admin.fms.funds.form', compact('fund'));
    }

    public function update(Request $request, Fund $fund)
    {
        $data = $request->validate([
            'fund_name' => 'required|string|max:255|unique:funds,fund_name,'.$fund->id,
            'fund_source' => 'nullable|string|max:255',
            'fund_type' => 'required|in:general,academic,scholarship,department,campus,emergency,special',
            'description' => 'nullable|string|max:5000',
            'status' => 'required|in:active,inactive',
        ]);
        $old = $fund->toArray();
        $fund->update($data);
        AuditService::log('update', 'funds', (string) $fund->id, $old, $fund->fresh()->toArray(), "Admin ".auth()->user()->name." updated Fund #{$fund->fund_name}.");
        return redirect()->route('admin.funds.show', $fund)->with('success', 'Fund updated.');
    }

    public function destroy(Fund $fund)
    {
        abort_if($fund->allocations()->exists(), 422, 'Fund has allocations — archive instead of delete.');
        abort_if($fund->transactions()->exists(), 422, 'Fund has transactions — archive instead of delete.');
        $old = $fund->toArray();
        $fund->delete();
        AuditService::log('delete', 'funds', (string) $fund->id, $old, null, "Admin ".auth()->user()->name." deleted Fund #{$old['fund_name']}.");
        return redirect()->route('admin.funds.index')->with('success', 'Fund deleted.');
    }

    public function transaction(Request $request, Fund $fund)
    {
        $data = $request->validate([
            'transaction_type' => 'required|in:inflow,outflow,reservation,release',
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'transaction_date' => 'required|date|before_or_equal:today',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($fund, $data) {
            $fund->lockForUpdate()->first();
            switch ($data['transaction_type']) {
                case 'inflow':
                    $fund->increment('current_balance', $data['amount']);
                    break;
                case 'outflow':
                    abort_if((float) $fund->current_balance - (float) $fund->reserved_amount < (float) $data['amount'], 422, 'Insufficient available balance.');
                    $fund->decrement('current_balance', $data['amount']);
                    break;
                case 'reservation':
                    abort_if((float) $fund->current_balance - (float) $fund->reserved_amount < (float) $data['amount'], 422, 'Insufficient available balance.');
                    $fund->increment('reserved_amount', $data['amount']);
                    break;
                case 'release':
                    abort_if((float) $fund->reserved_amount < (float) $data['amount'], 422, 'Release exceeds reserved amount.');
                    $fund->decrement('reserved_amount', $data['amount']);
                    break;
            }
            FundTransaction::create($data + ['fund_id' => $fund->id, 'created_by' => auth()->id()]);
        });
        AuditService::log('fund_transaction', 'funds', (string) $fund->id, null, null, "Admin ".auth()->user()->name." posted {$data['transaction_type']} of P".number_format($data['amount'], 2)." to Fund #{$fund->fund_name}.");
        return back()->with('success', 'Fund transaction posted.');
    }
}