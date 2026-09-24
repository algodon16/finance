@extends('layouts.admin')
@section('title', 'Fund Management and Allocation')
@section('content')
<div class="page-header">
    <h2>Fund Management and Allocation</h2>
    <a class="btn btn-primary" href="{{ route('admin.funds.create') }}">Create Fund</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Initial</h4><p class="val">P{{ number_format($totals['initial'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Current</h4><p class="val">P{{ number_format($totals['current'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Reserved</h4><p class="val">P{{ number_format($totals['reserved'], 2) }}</p></div>
    <div class="fms-stat"><h4>Available</h4><p class="val">P{{ number_format($totals['current'] - $totals['reserved'], 2) }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:240px;" placeholder="Search fund" value="{{ request('search') }}">
        <select name="fund_type" class="form-control" style="max-width:200px;"><option value="">All types</option>@foreach(['general','academic','scholarship','department','campus','emergency','special'] as $t)<option value="{{ $t }}" {{ request('fund_type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>@endforeach</select>
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.funds.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Fund Name</th><th>Type</th><th>Source</th><th style="text-align:right;">Initial</th><th style="text-align:right;">Current</th><th style="text-align:right;">Reserved</th><th style="text-align:right;">Used</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($funds as $f)
            <tr>
                <td><a href="{{ route('admin.funds.show', $f) }}">{{ $f->fund_name }}</a></td>
                <td>{{ ucfirst($f->fund_type) }}</td><td>{{ $f->fund_source ?? '—' }}</td>
                <td style="text-align:right;">P{{ number_format($f->initial_balance, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($f->current_balance, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($f->reserved_amount, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($f->used_amount, 2) }}</td>
                <td><span class="status {{ $f->status === 'active' ? 'st-green' : 'st-gray' }}">{{ ucfirst($f->status) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.funds.show', $f) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.funds.edit', $f) }}">Edit</a></div></td>
            </tr>
        @empty
            <tr><td colspan="9" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $funds->links() }}</div>
</div>
@endsection
