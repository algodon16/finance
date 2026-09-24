@extends('layouts.admin')
@section('title', 'Procurement and Financial Requests')
@section('content')
<div class="page-header">
    <h2>Procurement and Financial Requests</h2>
    <a class="btn btn-primary" href="{{ route('admin.procurement.create') }}">New Request</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Draft</h4><p class="val">{{ $summary['draft'] }}</p></div>
    <div class="fms-stat"><h4>Submitted</h4><p class="val">{{ $summary['submitted'] }}</p></div>
    <div class="fms-stat"><h4>Under Review</h4><p class="val">{{ $summary['under_review'] }}</p></div>
    <div class="fms-stat"><h4>Approved / Fulfilled</h4><p class="val">{{ $summary['approved'] }} / {{ $summary['fulfilled'] }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:260px;" placeholder="Search request no., dept, item" value="{{ request('search') }}">
        <select name="status" class="form-control" style="max-width:180px;"><option value="">All statuses</option>@foreach(['draft','submitted','under_review','approved','rejected','ordered','fulfilled','cancelled'] as $s)<option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.procurement.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Request No.</th><th>Department</th><th>Item / Service</th><th>Qty</th><th style="text-align:right;">Est. Cost</th><th>Supplier</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><a href="{{ route('admin.procurement.show', $r) }}">{{ $r->request_number ?? ('REQ-'.$r->id) }}</a></td>
                <td>{{ $r->requesting_department ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($r->item_description ?? '', 60) }}</td>
                <td>{{ $r->quantity ?? '—' }}</td>
                <td style="text-align:right;">P{{ number_format($r->estimated_cost ?? $r->total_amount ?? 0, 2) }}</td>
                <td>{{ $r->supplier ?? '—' }}</td>
                <td><span class="status">{{ ucwords(str_replace('_',' ',$r->status)) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.procurement.show', $r) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.procurement.edit', $r) }}">Edit</a></div></td>
            </tr>
        @empty
            <tr><td colspan="8" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
