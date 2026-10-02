@extends('layouts.admin')
@section('title', 'Admin Dashboard')
@section('content')
<div class="page-header">
    <h2>Admin Dashboard</h2>
    <span class="status">Financial Overview</span>
</div>

@if($totalRevenue == 0 && $totalExpenses == 0 && $accountsReceivable == 0)
    <div class="fms-panel"><p style="margin:0;color:#64748b;">No financial records available.</p></div>
@endif

<div class="fms-stat-grid">
    <a href="{{ route('admin.revenues.index') }}" class="fms-stat fms-stat-link" aria-label="Total Revenue - Go to Revenue Management">
        <h4>Total Revenue</h4><p class="val">₱{{ number_format($totalRevenue, 2) }}</p><p class="sub">Verified and reconciled payments</p>
    </a>
    <a href="{{ route('admin.expenses.index') }}" class="fms-stat fms-stat-link" aria-label="Total Expenses - Go to Expense and Disbursement Tracking">
        <h4>Total Expenses</h4><p class="val">₱{{ number_format($totalExpenses, 2) }}</p><p class="sub">Approved disbursements</p>
    </a>
    <a href="{{ route('admin.receivables.index') }}" class="fms-stat fms-stat-link" aria-label="Accounts Receivable - Go to Accounts Receivable Management">
        <h4>Accounts Receivable</h4><p class="val">₱{{ number_format($accountsReceivable, 2) }}</p><p class="sub">Assessed charges minus collections</p>
    </a>
    <a href="{{ route('admin.payables.index') }}" class="fms-stat fms-stat-link" aria-label="Accounts Payable - Go to Accounts Payable Management">
        <h4>Accounts Payable</h4><p class="val">₱{{ number_format($accountsPayable, 2) }}</p><p class="sub">Vendor obligations minus payments</p>
    </a>
    <a href="{{ route('admin.funds.index') }}" class="fms-stat fms-stat-link" aria-label="Available Funds - Go to Fund Management and Allocation">
        <h4>Available Funds</h4><p class="val">₱{{ number_format($availableFunds, 2) }}</p><p class="sub">Active fund balances</p>
    </a>
    <a href="{{ route('admin.receivables.index', ['sort' => 'outstanding_balance', 'dir' => 'desc']) }}" class="fms-stat fms-stat-link" aria-label="Outstanding Student Balances - Go to Accounts Receivable Management">
        <h4>Outstanding Student Balances</h4><p class="val">₱{{ number_format($outstandingBalances, 2) }}</p><p class="sub">Unpaid student accounts</p>
    </a>
    <a href="{{ route('admin.procurement.index') }}" class="fms-stat fms-stat-link" aria-label="Pending Financial Requests - Go to Procurement and Financial Requests">
        <h4>Pending Financial Requests</h4><p class="val">{{ number_format($pendingRequests) }}</p><p class="sub">Procurement requests awaiting action</p>
    </a>
    <a href="{{ route('admin.assets.index') }}" class="fms-stat fms-stat-link" aria-label="Total Institutional Assets - Go to Asset and Depreciation Management">
        <h4>Total Institutional Assets</h4><p class="val">₱{{ number_format($assetValue, 2) }}</p><p class="sub">Net book value (acquisition ₱{{ number_format($assetAcquisition, 2) }})</p>
    </a>
</div>

<div class="fms-panel">
    <h3>Pending Approvals by Module (same records Accountant submitted)</h3>
    <div class="fms-stat-grid">
        <a href="{{ route('admin.budgets.index', ['status' => 'submitted']) }}" class="fms-stat fms-stat-link"><h4>Pending Budget Plans</h4><p class="val">{{ $pendingBudgets ?? 0 }}</p></a>
        <a href="{{ route('admin.funds.index') }}" class="fms-stat fms-stat-link"><h4>Pending Fund Allocations</h4><p class="val">{{ $pendingAllocations ?? 0 }}</p></a>
        <a href="{{ route('admin.expenses.index', ['approval_status' => 'submitted']) }}" class="fms-stat fms-stat-link"><h4>Pending Expenses</h4><p class="val">{{ $pendingExpenses ?? 0 }}</p></a>
        <a href="{{ route('admin.payables.index', ['approval_status' => 'submitted']) }}" class="fms-stat fms-stat-link"><h4>Pending Payables</h4><p class="val">{{ $pendingPayables ?? 0 }}</p></a>
        <a href="{{ route('admin.financial-requests.index', ['status' => 'submitted']) }}" class="fms-stat fms-stat-link"><h4>Pending Financial Requests</h4><p class="val">{{ $pendingFinancialRequests ?? 0 }}</p></a>
        <a href="{{ route('admin.financial-requests.index', ['status' => 'approved']) }}" class="fms-stat fms-stat-link"><h4>Approved Requests</h4><p class="val">{{ $approvedRequests ?? 0 }}</p><p class="sub">₱{{ number_format($approvedAmount ?? 0, 2) }} authorized</p></a>
        <a href="{{ route('admin.expenses.index') }}" class="fms-stat fms-stat-link"><h4>Awaiting Financial Processing</h4><p class="val">{{ $awaitingProcessing ?? 0 }}</p><p class="sub">Approved, not yet completed</p></a>
        <a href="{{ route('admin.financial-requests.index', ['status' => 'completed']) }}" class="fms-stat fms-stat-link"><h4>Completed Transactions</h4><p class="val">{{ $completedTransactions ?? 0 }}</p><p class="sub">Actual posted to budget</p></a>
        <a href="{{ route('admin.reports.reconciliations', ['status' => 'submitted']) }}" class="fms-stat fms-stat-link"><h4>Pending Reconciliations</h4><p class="val">{{ $pendingReconciliations ?? 0 }}</p></a>
        <a href="{{ route('admin.budgets.index', ['status' => 'approved']) }}" class="fms-stat fms-stat-link"><h4>Approved Plans</h4><p class="val">{{ $approvedPlans ?? 0 }}</p></a>
        <a href="{{ route('admin.budgets.index', ['status' => 'rejected']) }}" class="fms-stat fms-stat-link"><h4>Rejected</h4><p class="val">{{ $rejectedCount ?? 0 }}</p></a>
    </div>
</div>

<div class="fms-analytics-grid">
    <div class="fms-panel fms-chart-card">
        <h3>Revenue Trend (Last 6 Months)</h3>
        <div class="fms-chart-wrap"><canvas id="revChart" aria-label="Revenue trend line chart" role="img"></canvas></div>
    </div>
    <div class="fms-panel fms-chart-card">
        <h3>Expense Trend (Last 6 Months)</h3>
        <div class="fms-chart-wrap"><canvas id="expChart" aria-label="Expense trend bar chart" role="img"></canvas></div>
    </div>
    <div class="fms-panel fms-chart-card">
        <h3>Budget Utilization ({{ $budgetYearLabel }})</h3>
        <div class="fms-donut-wrap">
            <canvas id="budgetChart" aria-label="Budget utilization donut chart" role="img"></canvas>
            <div class="fms-donut-center"><strong>{{ number_format($budgetPct, 1) }}%</strong><span>Utilized</span></div>
        </div>
        <div class="fms-budget-legend">
            <div><span>Used Budget</span><strong>₱{{ number_format($budgetUsed, 2) }}</strong></div>
            <div><span>Remaining Budget</span><strong>₱{{ number_format($budgetRemaining, 2) }}</strong></div>
            <div class="total"><span>Total Budget</span><strong>₱{{ number_format($budgetAllocated, 2) }}</strong></div>
        </div>
    </div>
</div>

<div class="fms-panel">
    <h3>Fund Allocation</h3>
    @forelse($fundAlloc as $f)
        @php $pct = ($fundTotal > 0) ? min(100, ((float) $f->current_balance / $fundTotal) * 100) : 0; @endphp
        <div style="margin-bottom:12px;font-size:0.85rem;">
            <div style="display:flex;justify-content:space-between;gap:12px;"><span>{{ $f->fund_name }}</span><span><strong>₱{{ number_format($f->current_balance, 2) }}</strong> <span style="color:#64748b;">({{ number_format($pct, 1) }}%)</span></span></div>
            <div class="fms-bar"><span style="width:{{ $pct }}%"></span></div>
        </div>
    @empty
        <p style="color:#64748b;">No financial records available.</p>
    @endforelse
</div>

<div class="fms-panel">
    <h3>Recent Revenue Transactions</h3>
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Transaction</th><th>Student</th><th>Date</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($recentPayments as $p)
            <tr>
                <td><a href="{{ route('admin.revenues.show', $p) }}">{{ $p->transaction_number ?? ('PAY-'.$p->id) }}</a></td>
                <td>{{ $p->student->full_name ?? 'Walk-in' }}</td>
                <td>{{ $p->payment_date }}</td>
                <td style="text-align:right;">₱{{ number_format($p->amount, 2) }}</td>
                <td><span class="status {{ $p->status === 'approved' || $p->status === 'verified' ? 'st-green' : ($p->status === 'rejected' ? 'st-red' : 'st-amber') }}">{{ ucfirst($p->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    const peso = (v) => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pesoShort = (v) => {
        v = Number(v);
        if (Math.abs(v) >= 1000000) return '₱' + (v / 1000000).toLocaleString('en-PH', { maximumFractionDigits: 1 }) + 'M';
        if (Math.abs(v) >= 1000) return '₱' + (v / 1000).toLocaleString('en-PH', { maximumFractionDigits: 1 }) + 'K';
        return '₱' + v.toLocaleString('en-PH');
    };
    const baseOpts = (color) => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0f2a5a',
                padding: 10,
                callbacks: { label: (c) => ' ' + peso(c.parsed.y) }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: '#eef2f7' }, ticks: { color: '#64748b', font: { size: 11 }, maxTicksLimit: 6, callback: (v) => pesoShort(v) } }
        }
    });

    const revLabels = @json($revenueLabels);
    const revFull = @json($revenueFullLabels);
    const revData = @json($revenueData);
    const expLabels = @json($expenseLabels);
    const expFull = @json($expenseFullLabels);
    const expData = @json($expenseData);

    const rc = document.getElementById('revChart');
    if (rc) {
        new Chart(rc, {
            type: 'line',
            data: { labels: revLabels, datasets: [{ data: revData, borderColor: '#1a56db', backgroundColor: '#1a56db', borderWidth: 2, tension: 0.35, fill: false, pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: '#1a56db', pointBorderColor: '#ffffff', pointBorderWidth: 1 }] },
            options: { ...baseOpts(), plugins: { ...baseOpts().plugins, tooltip: { backgroundColor: '#0f2a5a', padding: 10, callbacks: { title: (items) => revFull[items[0].dataIndex] || '', label: (c) => ' Revenue: ' + peso(c.parsed.y) } } } }
        });
    }
    const ec = document.getElementById('expChart');
    if (ec) {
        new Chart(ec, {
            type: 'bar',
            data: { labels: expLabels, datasets: [{ data: expData, backgroundColor: '#ea580c', borderRadius: 6, maxBarThickness: 38 }] },
            options: { ...baseOpts(), plugins: { ...baseOpts().plugins, tooltip: { backgroundColor: '#0f2a5a', padding: 10, callbacks: { title: (items) => expFull[items[0].dataIndex] || '', label: (c) => ' Expenses: ' + peso(c.parsed.y) } } } }
        });
    }
    const bc = document.getElementById('budgetChart');
    if (bc) {
        new Chart(bc, {
            type: 'doughnut',
            data: {
                labels: ['Used Budget', 'Remaining Budget'],
                datasets: [{ data: [@json($budgetUsed), @json($budgetRemaining)], backgroundColor: ['#1a56db', '#e2e8f0'], borderWidth: 0, hoverOffset: 4 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#0f2a5a', padding: 10, callbacks: { label: (c) => ' ' + c.label + ': ' + peso(c.parsed) } }
                }
            }
        });
    }
})();
</script>
@endpush
@endsection
