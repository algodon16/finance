@extends('layouts.admin')
@section('title', 'Accounts Receivable Management')
@section('content')
<div class="page-header">
    <h2>Accounts Receivable Management</h2>
    <a class="btn btn-primary" href="{{ route('admin.receivables.assess') }}">Post Fee Assessment</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Assessed</h4><p class="val">P{{ number_format($summary['assessed'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Collected</h4><p class="val">P{{ number_format($summary['collected'], 2) }}</p></div>
    <div class="fms-stat"><h4>Outstanding Balance</h4><p class="val">P{{ number_format($summary['outstanding'], 2) }}</p></div>
    <div class="fms-stat"><h4>Accounts With Balance</h4><p class="val">{{ number_format($summary['with_balance']) }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div><label style="font-size:0.8rem;">Search</label><br><input type="text" name="search" class="form-control" style="width:200px;" placeholder="Search student name or number" value="{{ request('search') }}"></div>
        <div><label style="font-size:0.8rem;">Status</label><br>
        <select name="status" class="form-control" style="min-width:160px;">
            <option value="">All statuses</option>
            <option value="cleared" {{ request('status') === 'cleared' ? 'selected' : '' }}>Fully Paid</option>
            <option value="not_cleared" {{ request('status') === 'not_cleared' ? 'selected' : '' }}>With Balance</option>
        </select></div>
        <div><button class="btn btn-secondary" type="submit">Filter</button> <a class="btn btn-secondary" href="{{ route('admin.receivables.index') }}">Reset</a></div>
    </form>
</div>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Student</th><th>Program</th><th style="text-align:right;">Assessed</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Remaining Balance</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td>{{ $r->student->full_name ?? '—' }}<br><small style="color:#64748b;">{{ $r->student->student_number ?? '' }}</small></td>
                <td>{{ $r->student->program ?? '—' }}</td>
                <td style="text-align:right;">P{{ number_format($r->total_charges, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($r->total_paid, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($r->outstanding_balance, 2) }}</td>
                <td><span class="status {{ $r->outstanding_balance <= 0 ? 'st-green' : ($r->total_paid > 0 ? 'st-amber' : 'st-red') }}">{{ $r->outstanding_balance <= 0 ? 'Fully Paid' : ($r->total_paid > 0 ? 'Partially Paid' : 'Current') }}</span></td>
                <td><a class="btn btn-sm btn-secondary" href="{{ route('admin.receivables.show', $r) }}">Ledger</a></td>
            </tr>
        @empty
            <tr><td colspan="7" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $records->links() }}</div>
</div>
@endsection
