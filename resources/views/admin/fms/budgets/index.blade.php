@extends('layouts.admin')
@section('title', 'Budget Planning and Allocation')
@section('content')
<div class="page-header">
    <h2>Budget Planning and Allocation</h2>
    <div class="no-print" style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.budgets.ai') }}">AI-Assisted Planning</a>
        <a class="btn btn-primary" href="{{ route('admin.budgets.create') }}">Create Budget Plan</a>
    </div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:260px;" placeholder="Search name or department" value="{{ request('search') }}">
        <select name="status" class="form-control" style="max-width:180px;">
            <option value="">All statuses</option>
            @foreach(['active','draft','inactive','closed'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="text" name="fiscal_year" class="form-control" style="max-width:160px;" placeholder="Fiscal year" value="{{ request('fiscal_year') }}">
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.budgets.index') }}">Reset</a>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Budget Name</th><th>Fiscal Year</th><th>Department</th><th>Category</th><th style="text-align:right;">Allocated</th><th style="text-align:right;">Utilized</th><th style="text-align:right;">Remaining</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($plans as $p)
            <tr>
                <td><a href="{{ route('admin.budgets.show', $p) }}">{{ $p->budget_name }}</a></td>
                <td>{{ $p->fiscal_year }}</td>
                <td>{{ $p->department ?? '—' }}</td>
                <td>{{ $p->budget_category }}</td>
                <td style="text-align:right;">P{{ number_format($p->allocated_amount, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($p->utilized_amount, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($p->remaining_amount, 2) }}</td>
                <td><span class="status {{ $p->status === 'active' ? 'st-green' : ($p->status === 'closed' ? 'st-gray' : 'st-amber') }}">{{ ucfirst($p->status) }}</span></td>
                <td><div class="fms-actions">
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.budgets.show', $p) }}">View</a>
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.budgets.edit', $p) }}">Edit</a>
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
