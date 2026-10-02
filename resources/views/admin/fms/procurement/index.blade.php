@extends('layouts.admin')
@section('title', 'Procurement and Financial Requests')
@section('content')
<div class="page-header">
    <div><h2>Procurement and Financial Requests</h2><p class="page-subtitle" style="color:#64748b;font-size:.85rem;">Accountant requests for admin approval — same records as Financial Requests Inbox.</p></div>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Draft</h4><p class="val">{{ $summary['draft'] }}</p></div>
    <div class="fms-stat"><h4>Submitted</h4><p class="val">{{ $summary['submitted'] }}</p></div>
    <div class="fms-stat"><h4>Under Review</h4><p class="val">{{ $summary['under_review'] }}</p></div>
    <div class="fms-stat"><h4>Approved / Fulfilled</h4><p class="val">{{ $summary['approved'] }} / {{ $summary['fulfilled'] }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div><label style="font-size:0.8rem;">Search</label><br><input type="text" name="search" class="form-control" style="width:200px;" placeholder="Search request ID, dept, purpose" value="{{ request('search') }}"></div>
        <div><label style="font-size:0.8rem;">Status</label><br>
        <select name="status" class="form-control" style="min-width:160px;">
            <option value="">All statuses</option>
            @foreach(['draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'for_revision' => 'For Revision', 'completed' => 'Completed'] as $s => $label)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><button class="btn btn-secondary" type="submit">Filter</button> <a class="btn btn-secondary" href="{{ route('admin.procurement.index') }}">Reset</a></div>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Request ID</th><th>Source</th><th>Department / Type</th><th style="text-align:right;">Amount</th><th>Prepared By</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><a href="{{ route('admin.financial-requests.show', $r) }}">{{ $r->display_ref }}</a>@if(!empty($r->metadata['demo'])) <span class="status st-red">Demo</span>@endif</td>
                <td>{{ $r->source_label }}</td>
                <td>{{ $r->department ?? '—' }}<br><span style="color:#64748b;font-size:.8rem;">{{ ucfirst(str_replace('_',' ',$r->request_type)) }}</span></td>
                <td style="text-align:right;">P{{ number_format($r->amount ?? 0, 2) }}</td>
                <td>{{ $r->preparer->name ?? '—' }}</td>
                <td><span class="status">{{ ucwords(str_replace('_',' ',$r->status)) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.financial-requests.show', $r) }}">Review</a></div></td>
            </tr>
        @empty
            <tr><td colspan="7" style="color:#64748b;">No financial requests from accountant yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
