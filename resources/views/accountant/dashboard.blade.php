@extends('layouts.app')

@section('title', 'Accountant Dashboard')

@section('content')
<div class="dash-welcome-row">
    <div>
        <h2>Welcome, {{ Auth::user()->name }}</h2>
        <p class="page-subtitle">Accountant · Financial analytics and monitoring · {{ $periodLabel ?? '' }}</p>
    </div>
    <span class="dash-datetime">{{ now()->format('M d, Y h:i A') }}</span>
</div>

<div class="summary-cards">
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Total Revenue</h3><p class="card-amount">₱{{ number_format($revenue ?? 0,2) }}</p><p class="summary-desc">@if(($revChange ?? 0) >= 0)<span style="color:#047857;">+{{ $revChange }}%</span>@else<span style="color:#b91c1c;">{{ $revChange }}%</span>@endif vs previous period</p></div></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Total Expenses</h3><p class="card-amount">₱{{ number_format($expenses ?? 0,2) }}</p><p class="summary-desc">@if(($expChange ?? 0) <= 0)<span style="color:#047857;">{{ $expChange }}%</span>@else<span style="color:#b91c1c;">+{{ $expChange }}%</span>@endif vs previous period</p></div></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Net Financial Position</h3><p class="card-amount">@if(($net ?? 0) >= 0)<span style="color:#047857;">₱{{ number_format($net,2) }}</span>@else<span style="color:#b91c1c;">−₱{{ number_format(abs($net),2) }}</span>@endif</p><p class="summary-desc">{{ ($net ?? 0) >= 0 ? 'Positive position' : 'Negative position' }} · revenue minus expenses</p></div></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Available Budget</h3><p class="card-amount">₱{{ number_format($availableBudget ?? 0,2) }}</p><p class="summary-desc">Approved budgets remaining</p></div></div>
</div>

<div class="summary-cards" style="margin-top:16px;">
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Budget Utilization</h3><p class="card-amount">{{ $budgetRate ?? 0 }}%</p><p class="summary-desc">₱{{ number_format($budgetUtilized ?? 0,2) }} of ₱{{ number_format($approvedBudget ?? 0,2) }}</p></div></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Total Funds Available</h3><p class="card-amount">₱{{ number_format($fundsAvailable ?? 0,2) }}</p><p class="summary-desc">Active funds, current balance</p></div></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Accounts Receivable</h3><p class="card-amount">₱{{ number_format($arOutstanding ?? 0,2) }}</p><p class="summary-desc">Outstanding student balances</p></div></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Accounts Payable</h3><p class="card-amount">₱{{ number_format($apOutstanding ?? 0,2) }}</p><p class="summary-desc">Unpaid obligations</p></div></div>
</div>

<div class="summary-cards" style="margin-top:16px;">
    <div class="dashboard-card acct-card"><a href="{{ route('accountant.financial-requests.index', ['status' => 'submitted']) }}" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>For Financial Review</h3><p class="card-amount">{{ $frForReview ?? 0 }}</p><p class="summary-desc">Submitted requests awaiting review</p></div></a></div>
    <div class="dashboard-card acct-card"><a href="{{ route('accountant.expenses.index') }}" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>For Processing</h3><p class="card-amount">{{ $frForProcessing ?? 0 }}</p><p class="summary-desc">Approved, in Expense &amp; Disbursement</p></div></a></div>
    <div class="dashboard-card acct-card"><a href="{{ route('accountant.expenses.index') }}" style="text-decoration:none;color:inherit;"><div class="card-content"><h3>Completed Transactions</h3><p class="card-amount">{{ $frCompleted ?? 0 }}</p><p class="summary-desc">Actual posted to budget</p></div></a></div>
    <div class="dashboard-card acct-card"><div class="card-content"><h3>Actual Expenses / Disbursed</h3><p class="card-amount">₱{{ number_format($actualExpenses ?? 0,2) }} / ₱{{ number_format($totalDisbursements ?? 0,2) }}</p><p class="summary-desc">Completed actuals vs total disbursements</p></div></div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
    <div class="card-head-row"><h3>Revenue vs Expenses</h3></div>
    <p class="page-subtitle">Monthly collections vs approved disbursements with net position trend.</p>
    <div style="position:relative;height:280px;"><canvas id="revExpChart" aria-label="Revenue versus expenses chart" role="img"></canvas></div>
</div>

<div class="two-col-grid" style="margin-top:16px;">
    <div class="dashboard-card">
        <div class="card-head-row"><h3>Budget Utilization</h3><a href="{{ route('accountant.budgets.overview') }}" class="view-all-link">View All</a></div>
        <div style="position:relative;height:220px;"><canvas id="budgetDonut" aria-label="Budget utilization chart" role="img"></canvas></div>
        <p style="margin-top:8px;">Approved Budget: <strong>₱{{ number_format($approvedBudget ?? 0,2) }}</strong></p>
        <p>Utilized: <strong>₱{{ number_format($budgetUtilized ?? 0,2) }}</strong> ({{ $budgetRate ?? 0 }}%)</p>
        <p>Remaining: <strong>₱{{ number_format($availableBudget ?? 0,2) }}</strong></p>
        <div class="progress-bar-container"><div class="progress-bar {{ ($budgetRate ?? 0) >= 90 ? 'red' : (($budgetRate ?? 0) >= 60 ? 'yellow' : 'green') }}" style="width:{{ min(100,$budgetRate ?? 0) }}%;">{{ $budgetRate ?? 0 }}%</div></div>
        <p class="summary-desc">Approved budgets only — unapproved requests never count toward utilization.</p>
    </div>
    <div class="dashboard-card">
        <div class="card-head-row"><h3>Expense Breakdown</h3><a href="{{ route('accountant.expenses.index') }}" class="view-all-link">View All</a></div>
        <div style="position:relative;height:220px;"><canvas id="expDonut" aria-label="Expense breakdown chart" role="img"></canvas></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Rank</th><th>Expense Category</th><th style="text-align:right;">Amount</th><th style="text-align:right;">%</th></tr></thead><tbody>
        @forelse($expBreakTop ?? [] as $i=>$r)<tr><td>{{ $i+1 }}</td><td>{{ $r['cat'] }}</td><td style="text-align:right;">₱{{ number_format($r['total'],2) }}</td><td style="text-align:right;">{{ $r['pct'] }}%</td></tr>@empty<tr><td colspan="4" class="text-center muted-text">No approved expenses in this period</td></tr>@endforelse
        @if(($expBreakOther ?? 0) > 0.009)<tr><td>—</td><td>Other</td><td style="text-align:right;">₱{{ number_format($expBreakOther,2) }}</td><td style="text-align:right;">{{ $expBreakTotal > 0 ? round(($expBreakOther/$expBreakTotal)*100,1) : 0 }}%</td></tr>@endif
        </tbody></table></div>
    </div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
    <div class="card-head-row"><h3>Department Budget Utilization</h3><a href="{{ route('accountant.budgets.overview') }}" class="view-all-link">View All</a></div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Department</th><th style="text-align:right;">Approved Budget</th><th style="text-align:right;">Utilized</th><th style="text-align:right;">Remaining</th><th style="min-width:160px;">Utilization %</th></tr></thead><tbody>
    @forelse($deptRows ?? [] as $d)<tr><td><strong>{{ $d['department'] }}</strong></td><td style="text-align:right;">₱{{ number_format($d['allocated'],2) }}</td><td style="text-align:right;">₱{{ number_format($d['utilized'],2) }}</td><td style="text-align:right;">₱{{ number_format($d['remaining'],2) }}</td><td><div class="progress-bar-container"><div class="progress-bar {{ $d['rate'] >= 90 ? 'red' : ($d['rate'] >= 60 ? 'yellow' : 'green') }}" style="width:{{ min(100,$d['rate']) }}%;">{{ $d['rate'] }}%</div></div></td></tr>
    @empty<tr><td colspan="5" class="text-center muted-text">No approved department budgets</td></tr>@endforelse
    </tbody></table></div>
</div>

<p class="page-subtitle">Revenue, expenses, and breakdowns cover this month. Budget, fund, receivable, and payable positions are current point-in-time balances.</p>
<div class="dashboard-card" style="margin-top:16px;">
    <div class="card-head-row"><h3>Fund Utilization</h3><a href="{{ route('accountant.fund-allocations.index') }}" class="view-all-link">View All</a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Fund</th><th style="text-align:right;">Allocated</th><th style="text-align:right;">Reserved</th><th style="text-align:right;">Available</th><th style="text-align:right;">Util %</th></tr></thead><tbody>
        @forelse($fundRows ?? [] as $f)<tr><td><strong>{{ $f['name'] }}</strong><br><span class="summary-desc">Total ₱{{ number_format($f['total'],2) }}</span></td><td style="text-align:right;">₱{{ number_format($f['allocated'],2) }}</td><td style="text-align:right;">₱{{ number_format($f['reserved'],2) }}</td><td style="text-align:right;"><strong>₱{{ number_format($f['available'],2) }}</strong></td><td style="text-align:right;">{{ $f['rate'] }}%</td></tr>
        @empty<tr><td colspan="5" class="text-center muted-text">No funds</td></tr>@endforelse
        </tbody></table></div>
</div>

<div class="two-col-grid" style="margin-top:16px;">
    <div class="dashboard-card">
        <div class="card-head-row"><h3>Accounts Receivable</h3><a href="{{ route('accountant.accounts-receivable.index') }}" class="view-all-link">View All</a></div>
        <p>Total Receivables: <strong>₱{{ number_format($arAssessed ?? 0,2) }}</strong></p>
        <p>Collected: <strong>₱{{ number_format($arCollected ?? 0,2) }}</strong></p>
        <p>Outstanding: <strong>₱{{ number_format($arOutstanding ?? 0,2) }}</strong></p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Program</th><th style="text-align:right;">Outstanding</th></tr></thead><tbody>
        @forelse($arByProgram ?? [] as $r)<tr><td>{{ $r->program ?? 'N/A' }}</td><td style="text-align:right;"><strong>₱{{ number_format($r->outstanding,2) }}</strong></td></tr>
        @empty<tr><td colspan="2" class="text-center muted-text">No outstanding balances</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="dashboard-card">
        <div class="card-head-row"><h3>Accounts Payable</h3><a href="{{ route('accountant.payables.index') }}" class="view-all-link">View All</a></div>
        <p>Total Payables: <strong>₱{{ number_format($apTotal ?? 0,2) }}</strong></p>
        <p>Paid: <strong>₱{{ number_format($apPaid ?? 0,2) }}</strong></p>
        <p>Outstanding: <strong>₱{{ number_format($apOutstanding ?? 0,2) }}</strong> · Overdue: <strong>₱{{ number_format($apOverdue ?? 0,2) }}</strong></p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Aging Bucket</th><th style="text-align:right;">Outstanding</th></tr></thead><tbody>
        @foreach(($apAging ?? []) as $bucket=>$amt)<tr><td>{{ $bucket }}</td><td style="text-align:right;">₱{{ number_format($amt,2) }}</td></tr>@endforeach
        </tbody></table></div>
    </div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
    <div class="card-head-row"><h3>Recent Financial Transactions</h3><a href="{{ route('accountant.revenue.index') }}" class="view-all-link">View All</a></div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Reference No.</th><th>Transaction Type</th><th>Description</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
    @forelse($recentTx ?? [] as $t)<tr><td>{{ $t->date ? \Carbon\Carbon::parse($t->date)->format('M d, Y') : 'N/A' }}</td><td><strong>{{ $t->ref }}</strong></td><td>{{ $t->type }}</td><td>{{ \Str::limit($t->desc ?? '', 50) }}</td><td style="text-align:right;">₱{{ number_format($t->amount,2) }}</td><td><span class="badge {{ $t->type === 'Revenue' ? 'badge-green' : 'badge-blue' }}">{{ $t->status }}</span></td></tr>
    @empty<tr><td colspan="6" class="text-center muted-text">No financial transactions found</td></tr>@endforelse
    </tbody></table></div>
    <p class="summary-desc">Actual postings only. Workflow and audit logs remain under Security and Audit Trail.</p>
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
    const grid = { color: '#eef2f7' };
    const ticks = { color: '#64748b', font: { size: 11 } };

    const labels = @json($monthLabels ?? []);
    const full = @json($monthFull ?? []);
    const rev = @json($revData ?? []);
    const exp = @json($expData ?? []);
    const net = @json($netData ?? []);

    const rc = document.getElementById('revExpChart');
    if (rc) {
        new Chart(rc, {
            type: 'bar',
            data: { labels: labels, datasets: [
                { label: 'Revenue', data: rev, backgroundColor: '#1a56db', borderRadius: 4 },
                { label: 'Expenses', data: exp, backgroundColor: '#f59e0b', borderRadius: 4 },
                { label: 'Net', data: net, type: 'line', borderColor: '#059669', backgroundColor: '#059669', borderWidth: 2, tension: 0.35, pointRadius: 3 }
            ]},
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#374151', font: { size: 11 }, boxWidth: 12 } },
                    tooltip: { backgroundColor: '#0f2a5a', padding: 10, callbacks: { title: (items) => full[items[0].dataIndex] || '', label: (c) => ' ' + c.dataset.label + ': ' + peso(c.parsed.y) } } },
                scales: { x: { grid: { display: false }, ticks: ticks }, y: { beginAtZero: true, grid: grid, ticks: { ...ticks, maxTicksLimit: 6, callback: (v) => pesoShort(v) } } } }
        });
    }

    const bc = document.getElementById('budgetDonut');
    if (bc) {
        new Chart(bc, { type: 'doughnut',
            data: { labels: ['Utilized', 'Remaining'], datasets: [{ data: [{{ $budgetUtilized ?? 0 }}, {{ $availableBudget ?? 0 }}], backgroundColor: ['#1a56db', '#e2e8f0'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { color: '#374151', font: { size: 11 }, boxWidth: 12 } },
                    tooltip: { backgroundColor: '#0f2a5a', padding: 10, callbacks: { label: (c) => ' ' + c.label + ': ' + peso(c.parsed) } } } } });
    }

    const ec = document.getElementById('expDonut');
    if (ec) {
        new Chart(ec, { type: 'doughnut',
            data: { labels: @json(collect($expBreakTop ?? [])->pluck('cat')), datasets: [{ data: @json(collect($expBreakTop ?? [])->pluck('total')), backgroundColor: ['#1a56db', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#be123c'], borderWidth: 2, borderColor: '#ffffff' }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: { legend: { position: 'bottom', labels: { color: '#374151', font: { size: 11 }, boxWidth: 12 } },
                    tooltip: { backgroundColor: '#0f2a5a', padding: 10, callbacks: { label: (c) => ' ' + c.label + ': ' + peso(c.parsed) } } } } });
    }
})();
</script>
@endpush
@endsection
