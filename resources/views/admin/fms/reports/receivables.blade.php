@extends('layouts.admin')
@section('title', 'Receivables Report')
@section('content')
<div class="page-header"><h2>Outstanding Receivables Report</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-panel">
    <p><strong>Total Outstanding:</strong> P{{ number_format($total, 2) }}</p>
    <table class="fms-table"><thead><tr><th>Student</th><th>Number</th><th>Program</th><th style="text-align:right;">Assessed</th><th style="text-align:right;">Paid</th><th style="text-align:right;">Outstanding</th></tr></thead><tbody>
    @forelse($records as $r)<tr><td>{{ $r->student->full_name ?? '—' }}</td><td>{{ $r->student->student_number ?? '—' }}</td><td>{{ $r->student->program ?? '—' }}</td><td style="text-align:right;">P{{ number_format($r->total_charges, 2) }}</td><td style="text-align:right;">P{{ number_format($r->total_paid, 2) }}</td><td style="text-align:right;">P{{ number_format($r->outstanding_balance, 2) }}</td></tr>
    @empty<tr><td colspan="6" style="color:#64748b;">No financial records available.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
