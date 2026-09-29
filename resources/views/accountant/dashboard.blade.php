@extends('layouts.app')

@section('title', 'Accountant Dashboard')

@section('content')
<div class="dash-welcome-row">
    <div>
        <h2>Welcome, {{ Auth::user()->name }}</h2>
        <p class="page-subtitle">Prepare financial data — Admin reviews and approves the same records in the matching modules.</p>
    </div>
    <span class="dash-datetime">{{ now()->format('M d, Y h:i A') }}</span>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Collections</h3>
            <p class="card-amount">₱{{ number_format($totalCollections ?? 0, 2) }}</p>
            <p class="summary-desc">Total amount collected</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Outstanding Balance</h3>
            <p class="card-amount">₱{{ number_format($outstandingBalance ?? 0, 2) }}</p>
            <p class="summary-desc">Unpaid balances</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Total Payments</h3>
            <p class="card-amount">{{ number_format($totalPayments ?? 0) }}</p>
            <p class="summary-desc">Total number of payments</p>
        </div>
    </div>

    <div class="dashboard-card acct-card">
        <div class="card-content">
            <h3>Unreconciled Transactions</h3>
            <p class="card-amount">{{ number_format($unreconciledCount ?? 0) }}</p>
            <p class="summary-desc">Transactions for review</p>
        </div>
    </div>

    <a href="{{ route('accountant.submissions.pending') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;">
        <div class="card-content">
            <h3>Pending Admin Approval</h3>
            <p class="card-amount">{{ number_format($pendingApproval ?? 0) }}</p>
            <p class="summary-desc">Awaiting admin decision</p>
        </div>
    </a>

    <a href="{{ route('accountant.submissions.approved') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;">
        <div class="card-content">
            <h3>Approved Plans</h3>
            <p class="card-amount">{{ number_format($approvedPlans ?? 0) }}</p>
            <p class="summary-desc">Approved by admin</p>
        </div>
    </a>

    <a href="{{ route('accountant.submissions.rejected') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;">
        <div class="card-content">
            <h3>Rejected / For Revision</h3>
            <p class="card-amount">{{ number_format($rejectedPlans ?? 0) }}</p>
            <p class="summary-desc">Needs revision</p>
        </div>
    </a>

    <a href="{{ route('accountant.budgets.index') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;">
        <div class="card-content">
            <h3>Total Budget Proposals</h3>
            <p class="card-amount">{{ number_format($totalBudgetProposals ?? 0) }}</p>
            <p class="summary-desc">All proposals</p>
        </div>
    </a>

    <a href="{{ route('accountant.expenses.index') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;">
        <div class="card-content">
            <h3>Total Expense Proposals</h3>
            <p class="card-amount">{{ number_format($totalExpenseProposals ?? 0) }}</p>
            <p class="summary-desc">All proposals</p>
        </div>
    </a>
</div>

<div class="dashboard-card">
    <div class="card-head-row">
        <h3>Pending Approval Summary</h3>
        <a href="{{ route('accountant.submissions.pending') }}" class="view-all-link">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Category</th><th>Pending Count</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($pendingBreakdown ?? [] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td><strong>{{ $row['count'] }}</strong></td>
                        <td><a href="{{ route($row['route']) }}" class="btn btn-sm btn-secondary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center">No pending submissions</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-card">
    <div class="card-head-row">
        <h3>Recent Activity — Submissions, Approvals, Rejections &amp; Audit</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Type</th><th>Description</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
                @foreach(($recentSubmissions ?? collect())->take(3) as $r)
                    <tr><td>Submitted</td><td>{{ $r->type }} — {{ $r->desc }}</td><td>₱{{ number_format($r->amount, 2) }}</td><td>{{ $r->date ? \Carbon\Carbon::parse($r->date)->format('M d, Y') : 'N/A' }}</td><td><span class="badge badge-yellow">{{ $r->status }}</span></td></tr>
                @endforeach
                @foreach(($recentApproved ?? collect())->take(3) as $r)
                    <tr><td>Approved</td><td>{{ $r->type }} — {{ $r->desc }}</td><td>₱{{ number_format($r->amount, 2) }}</td><td>{{ $r->date ? \Carbon\Carbon::parse($r->date)->format('M d, Y') : 'N/A' }}</td><td><span class="badge badge-green">{{ $r->status }}</span></td></tr>
                @endforeach
                @foreach(($recentRejected ?? collect())->take(3) as $r)
                    <tr><td>Rejected</td><td>{{ $r->type }} — {{ $r->desc }}</td><td>₱{{ number_format($r->amount, 2) }}</td><td>{{ $r->date ? \Carbon\Carbon::parse($r->date)->format('M d, Y') : 'N/A' }}</td><td><span class="badge badge-red">{{ $r->status }}</span></td></tr>
                @endforeach
                @foreach(($recentAudits ?? collect())->take(5) as $a)
                    <tr><td>Audit: {{ $a->action }}</td><td>{{ $a->description ?? ($a->module.' #'.$a->record_id) }}</td><td>—</td><td>{{ $a->created_at?->format('M d, Y h:i A') }}</td><td><span class="badge badge-gray">{{ $a->module }}</span></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="dashboard-card">
    <div class="card-head-row">
        <h3>Recent Financial Transactions</h3>
        <a href="{{ route('accountant.revenue.index') }}" class="view-all-link">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>ID No.</th>
                    <th>Name</th>
                    <th>Payment For</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentTransactions ?? [] as $payment)
                    <tr>
                        <td>{{ $payment->created_at ? $payment->created_at->format('M d, Y h:i A') : ($payment->payment_date ? $payment->payment_date->format('M d, Y') : 'N/A') }}</td>
                        <td>{{ $payment->student->student_number ?? 'N/A' }}</td>
                        <td>{{ $payment->student->full_name ?? 'N/A' }}</td>
                        <td>{{ $payment->description ?? 'N/A' }}</td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '')) }}</td>
                        <td>{{ $payment->reference_number ?? 'N/A' }}</td>
                        <td>
                            @if(in_array($payment->status, ['approved', 'posted']))
                                <span class="badge badge-green">{{ ucfirst($payment->status) }}</span>
                            @elseif($payment->status === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif($payment->status === 'rejected')
                                <span class="badge badge-red">Rejected</span>
                            @else
                                <span class="badge badge-{{ $payment->status }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No financial transactions found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="two-col-grid">
    <div class="dashboard-card">
        <div class="card-head-row">
            <h3>Outstanding Balance Overview</h3>
            <a href="{{ route('accountant.accounts-receivable.index') }}" class="view-all-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Total Assessment</th>
                        <th>Total Paid</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($outstandingByProgram ?? [] as $row)
                        <tr>
                            <td>{{ $row->program ?? 'N/A' }}</td>
                            <td>₱{{ number_format($row->assessment, 2) }}</td>
                            <td>₱{{ number_format($row->paid, 2) }}</td>
                            <td><strong>₱{{ number_format($row->outstanding, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No outstanding balances</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-head-row">
            <h3>Reconciliation Summary</h3>
            <a href="{{ route('accountant.reconciliation.index') }}" class="view-all-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>System Amount</th>
                        <th>Actual Amount</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliationSummary ?? [] as $row)
                        <tr>
                            <td>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('M d, Y') : 'N/A' }}</td>
                            <td>₱{{ number_format($row->system_amount, 2) }}</td>
                            <td>₱{{ number_format($row->actual_amount, 2) }}</td>
                            <td>₱{{ number_format($row->difference, 2) }}</td>
                            <td>
                                @if($row->status === 'Reconciled')
                                    <span class="badge badge-green">Reconciled</span>
                                @elseif($row->status === 'Variance')
                                    <span class="badge badge-red">Variance</span>
                                @else
                                    <span class="badge badge-yellow">Unreconciled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No reconciliation records</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="summary-cards" style="margin-top:16px;">
<a href="{{ route('accountant.fund-allocations.index') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>Available Funds</h3><p class="card-amount">₱{{ number_format($availableFunds ?? 0,2) }}</p><p class="summary-desc">Active funds</p></div></a>
<a href="{{ route('accountant.budgets.index') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>Approved Budget</h3><p class="card-amount">₱{{ number_format($approvedBudget ?? 0,2) }}</p><p class="summary-desc">Approved / active</p></div></a>
<a href="{{ route('accountant.fund-allocations.index') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>Allocated Funds</h3><p class="card-amount">₱{{ number_format($allocatedFunds ?? 0,2) }}</p><p class="summary-desc">Approved allocations</p></div></a>
<a href="{{ route('accountant.budgets.index') }}" class="dashboard-card acct-card" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>Remaining Budget</h3><p class="card-amount">₱{{ number_format($remainingBudget ?? 0,2) }}</p><p class="summary-desc">Approved − utilized</p></div></a>
</div>

<div class="two-col-grid" style="margin-top:16px;">
<div class="dashboard-card"><div class="card-head-row"><h3>Budget Utilization</h3><a href="{{ route('accountant.budgets.index') }}" class="view-all-link">View All</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Plan</th><th>Allocated</th><th>Used</th><th>Remaining</th><th>Rate</th></tr></thead><tbody>
@forelse($budgetUtilization ?? [] as $b)<tr><td>{{ $b->budget_name }}</td><td>₱{{ number_format($b->allocated_amount,2) }}</td><td>₱{{ number_format($b->utilized_amount,2) }}</td><td>₱{{ number_format($b->remaining_amount,2) }}</td><td>{{ $b->utilization_rate }}%</td></tr>
@empty<tr><td colspan="5" class="text-center">No approved budgets</td></tr>@endforelse
</tbody></table></div></div>
<div class="dashboard-card"><div class="card-head-row"><h3>Fund Allocation Overview</h3><a href="{{ route('accountant.fund-allocations.index') }}" class="view-all-link">View All</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Fund</th><th>Current</th><th>Reserved</th><th>Available</th></tr></thead><tbody>
@forelse($fundOverview ?? [] as $f)<tr><td>{{ $f->fund_name }}</td><td>₱{{ number_format($f->current_balance,2) }}</td><td>₱{{ number_format($f->reserved_amount,2) }}</td><td><strong>₱{{ number_format($f->available_amount,2) }}</strong></td></tr>
@empty<tr><td colspan="4" class="text-center">No funds</td></tr>@endforelse
</tbody></table></div></div>
</div>

<div class="two-col-grid" style="margin-top:16px;">
<div class="dashboard-card"><div class="card-head-row"><h3>Expense &amp; Disbursement Summary</h3><a href="{{ route('accountant.expenses.index') }}" class="view-all-link">View All</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Reference</th><th>Payee</th><th>Amount</th><th>Status</th></tr></thead><tbody>
@forelse($expenseSummary ?? [] as $e)<tr><td>{{ $e->reference_number }}</td><td>{{ $e->payee }}</td><td>₱{{ number_format($e->amount,2) }}</td><td>@include('accountant.partials.status-badge',['status'=>$e->approval_status])</td></tr>
@empty<tr><td colspan="4" class="text-center">No approved expenses</td></tr>@endforelse
</tbody></table></div></div>
<div class="dashboard-card"><div class="card-head-row"><h3>Accounts Payable Summary</h3><a href="{{ route('accountant.payables.index') }}" class="view-all-link">View All</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Invoice</th><th>Vendor</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
@forelse($payableSummary ?? [] as $p)<tr><td>{{ $p->invoice_number }}</td><td>{{ $p->vendor }}</td><td>₱{{ number_format($p->remaining_balance,2) }}</td><td>@include('accountant.partials.status-badge',['status'=>$p->approval_status ?? 'draft'])</td></tr>
@empty<tr><td colspan="4" class="text-center">No payables</td></tr>@endforelse
</tbody></table></div></div>
</div>

<div class="two-col-grid" style="margin-top:16px;">
<div class="dashboard-card"><div class="card-head-row"><h3>Recent Approved Plans</h3><a href="{{ route('accountant.submissions.approved') }}" class="view-all-link">View All</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Plan</th><th>Amount</th><th>Date</th></tr></thead><tbody>
@forelse($recentApprovedPlans ?? [] as $b)<tr><td>{{ $b->budget_name }}</td><td>₱{{ number_format($b->allocated_amount,2) }}</td><td>{{ optional($b->approved_at)->format('M d, Y') }}</td></tr>
@empty<tr><td colspan="3" class="text-center">None</td></tr>@endforelse
</tbody></table></div></div>
<div class="dashboard-card"><div class="card-head-row"><h3>Recent Rejected / For Revision</h3><a href="{{ route('accountant.submissions.rejected') }}" class="view-all-link">View All</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Plan</th><th>Reason</th><th>Date</th></tr></thead><tbody>
@forelse($recentRejectedPlans ?? [] as $b)<tr><td>{{ $b->budget_name }}</td><td>{{ $b->rejection_reason ?? '—' }}</td><td>{{ optional($b->reviewed_at)->format('M d, Y') }}</td></tr>
@empty<tr><td colspan="3" class="text-center">None</td></tr>@endforelse
</tbody></table></div></div>
</div>
@endsection
