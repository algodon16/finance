@extends('layouts.admin')
@section('title', 'Asset Register Report')
@section('content')
<div class="page-header"><h2>Asset Register and Depreciation Report</h2>
    <div class="no-print" style="display:flex;gap:8px;"><a class="btn btn-secondary" href="{{ route('admin.reports.index') }}">All Reports</a><button class="btn btn-primary" onclick="window.print();">Print / PDF</button></div></div>
<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Acquisition Cost</h4><p class="val">P{{ number_format($totals['acquisition'], 2) }}</p></div>
    <div class="fms-stat"><h4>Accumulated Depreciation</h4><p class="val">P{{ number_format($totals['accumulated'], 2) }}</p></div>
    <div class="fms-stat"><h4>Net Book Value</h4><p class="val">P{{ number_format($totals['book'], 2) }}</p></div>
</div>
<div class="fms-panel">
    <table class="fms-table"><thead><tr><th>Asset ID</th><th>Name</th><th>Acquired</th><th style="text-align:right;">Cost</th><th style="text-align:right;">Annual Dep.</th><th style="text-align:right;">Acc. Dep.</th><th style="text-align:right;">Book Value</th><th>Status</th></tr></thead><tbody>
    @forelse($records as $r)<tr><td>{{ $r->asset_code }}</td><td>{{ $r->asset_name }}</td><td>{{ $r->acquisition_date?->format('Y-m-d') }}</td><td style="text-align:right;">P{{ number_format($r->acquisition_cost, 2) }}</td><td style="text-align:right;">P{{ number_format($r->annual_depreciation, 2) }}</td><td style="text-align:right;">P{{ number_format($r->accumulated_depreciation, 2) }}</td><td style="text-align:right;">P{{ number_format($r->book_value, 2) }}</td><td>{{ ucfirst($r->asset_status) }}</td></tr>
    @empty<tr><td colspan="8" style="color:#64748b;">No financial records available.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
