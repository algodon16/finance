@extends('layouts.admin')
@section('title', 'Budget vs Actual Report')
@section('content')
<div class="page-header"><h2>Budget vs Actual Report</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-panel">
    <table class="fms-table"><thead><tr><th>Budget Plan</th><th>Fiscal Year</th><th style="text-align:right;">Allocated</th><th style="text-align:right;">Utilized</th><th style="text-align:right;">Remaining</th><th style="text-align:right;">Utilization</th></tr></thead><tbody>
    @forelse($plans as $p)<tr><td>{{ $p->budget_name }}</td><td>{{ $p->academic_year }}</td><td style="text-align:right;">P{{ number_format($p->allocated_amount, 2) }}</td><td style="text-align:right;">P{{ number_format($p->utilized_amount, 2) }}</td><td style="text-align:right;">P{{ number_format($p->remaining_amount, 2) }}</td><td style="text-align:right;">{{ $p->utilization_rate }}%</td></tr>
    @empty<tr><td colspan="6" style="color:#64748b;">No financial records available.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
