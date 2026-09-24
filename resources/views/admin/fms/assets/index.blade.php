@extends('layouts.admin')
@section('title', 'Asset and Depreciation Management')
@section('content')
<div class="page-header">
    <h2>Asset and Depreciation Management</h2>
    <a class="btn btn-primary" href="{{ route('admin.assets.create') }}">Register Asset</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Assets</h4><p class="val">{{ number_format($summary['count']) }}</p></div>
    <div class="fms-stat"><h4>Acquisition Cost</h4><p class="val">P{{ number_format($summary['acquisition'], 2) }}</p></div>
    <div class="fms-stat"><h4>Accumulated Depreciation</h4><p class="val">P{{ number_format($summary['accumulated'], 2) }}</p></div>
    <div class="fms-stat"><h4>Current Book Value</h4><p class="val">P{{ number_format($summary['book'], 2) }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:240px;" placeholder="Search name, code, serial" value="{{ request('search') }}">
        <select name="asset_status" class="form-control" style="max-width:180px;"><option value="">All statuses</option>@foreach(['active','maintenance','disposed','lost','retired'] as $s)<option value="{{ $s }}" {{ request('asset_status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>@endforeach</select>
        <input type="text" name="asset_category" class="form-control" style="max-width:180px;" placeholder="Category" value="{{ request('asset_category') }}">
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.assets.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Asset ID</th><th>Name</th><th>Category</th><th style="text-align:right;">Cost</th><th style="text-align:right;">Acc. Depreciation</th><th style="text-align:right;">Book Value</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><a href="{{ route('admin.assets.show', $r) }}">{{ $r->asset_code }}</a></td>
                <td>{{ $r->asset_name }}</td><td>{{ $r->asset_category }}</td>
                <td style="text-align:right;">P{{ number_format($r->acquisition_cost, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($r->accumulated_depreciation, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($r->book_value, 2) }}</td>
                <td><span class="status {{ $r->asset_status === 'active' ? 'st-green' : ($r->asset_status === 'maintenance' ? 'st-amber' : 'st-gray') }}">{{ ucwords(str_replace('_',' ',$r->asset_status)) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.assets.show', $r) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.assets.edit', $r) }}">Edit</a></div></td>
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
