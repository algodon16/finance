@extends('layouts.admin')
@section('title', 'Budget Planning and Allocation Overview')
@section('content')
<div class="page-header">
    <div><h2>Budget Planning and Allocation Overview</h2><p style="color:#64748b;margin:4px 0 0;">All budget plans with proposed totals and approval status.</p></div>
    <div class="no-print" style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.budgets.requests') }}">Budget Requests Inbox</a>
        <a class="btn btn-secondary" href="{{ route('admin.budgets.ai') }}">AI-Assisted Planning</a>
        <a class="btn btn-primary" href="{{ route('admin.budgets.create') }}">Create Budget Plan</a>
    </div>
</div>

<div class="fms-stat-grid">
<div class="fms-stat"><h4>Total Proposed</h4><p class="val">P{{ number_format($counts['total_proposed'] ?? 0, 2) }}</p></div>
<div class="fms-stat"><h4>Pending / Submitted</h4><p class="val">{{ $counts['pending'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>Approved</h4><p class="val">{{ $counts['approved'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>Rejected / For Revision</h4><p class="val">{{ $counts['rejected'] ?? 0 }}</p></div>
<div class="fms-stat"><h4>All</h4><p class="val">{{ $counts['all'] ?? $plans->total() }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div><label style="font-size:0.8rem;">Search Plan</label><br><input type="text" name="search" class="form-control" style="min-width:220px;" placeholder="Plan name / department / program" value="{{ request('search') }}"></div>
        <div><label style="font-size:0.8rem;">Academic Year</label><br>
        <select name="academic_year" class="form-control" style="min-width:170px;">
            <option value="">All Academic Years</option>
            @foreach(($years ?? []) as $y)
                <option value="{{ $y }}" {{ request('academic_year') === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
        </select></div>
        <div><label style="font-size:0.8rem;">Status</label><br>
        <select name="status" class="form-control" style="max-width:180px;">
            <option value="">All statuses</option>
            @foreach(['draft'=>'Draft','submitted'=>'Submitted','under_review'=>'Under Review','approved'=>'Approved','active'=>'Active','for_revision'=>'For Revision','revision'=>'Revision','rejected'=>'Rejected','cancelled'=>'Cancelled','inactive'=>'Inactive','closed'=>'Closed'] as $k=>$l)
                <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
        </select></div>
        <div><button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.budgets.index') }}">Clear</a></div>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Budget ID</th><th>Plan</th><th>Academic Year</th><th>Department</th><th style="text-align:right;">Quantity</th><th style="text-align:right;">Total Proposed</th><th style="text-align:right;">Total</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($plans as $p)
            @php $qty = $p->items ? $p->items->sum('quantity') : 0; @endphp
            <tr>
                <td><strong>#{{ $p->id }}</strong></td>
                <td><a href="{{ route('admin.budgets.show', $p) }}"><strong>{{ $p->budget_name }}</strong></a><br><span style="color:#64748b;font-size:.8rem;">{{ $p->budget_category }}</span></td>
                <td>{{ $p->academic_year }}</td>
                <td>{{ $p->department ?? '—' }}</td>
                <td style="text-align:right;">{{ number_format($qty) }}</td>
                <td style="text-align:right;font-weight:700;">P{{ number_format($p->allocated_amount, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($p->allocated_amount, 2) }}</td>
                <td><span class="status {{ $p->status === 'active' ? 'st-green' : ($p->status === 'closed' ? 'st-gray' : 'st-amber') }}">{{ ucfirst($p->status) }}</span></td>
                <td><div class="fms-actions" style="white-space:nowrap;display:flex;gap:4px;">
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.budgets.show', $p) }}">View</a>
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.budgets.edit', $p) }}">Edit</a>
                    <form method="POST" action="{{ route('admin.budgets.destroy', $p) }}" onsubmit="return confirm('Delete this budget plan? This cannot be undone.');" style="display:inline;">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="9" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $plans->links() }}</div>
</div>
@endsection
