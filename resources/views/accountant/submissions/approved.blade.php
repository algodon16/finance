@extends('layouts.app')
@section('title', 'Approved Plans')
@section('content')
<div class="page-header"><div><h2>Approved Plans</h2><p class="page-subtitle">View-only. Utilization and linked transactions shown. Post-approval changes need a controlled revision.</p></div></div>
<div class="dashboard-card"><div class="table-responsive"><table class="table"><thead><tr><th>Reference No.</th><th>Type</th><th>Approved Amount</th><th>Utilization / Paid</th><th>Remaining</th><th>Approved By</th><th>Approval Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($items as $i)<tr><td><strong>{{ $i['ref'] }}</strong><br><span class="summary-desc">{{ $i['desc'] }}</span></td><td>{{ $i['type'] }}</td><td>₱{{ number_format($i['amount'] ?? 0,2) }}</td><td>{{ $i['used'] !== null ? '₱'.number_format($i['used'],2) : '—' }}</td><td>{{ $i['remaining'] !== null ? '₱'.number_format($i['remaining'],2) : '—' }}</td><td>{{ $i['by'] }}</td><td>{{ $i['approved_at'] ? \Carbon\Carbon::parse($i['approved_at'])->format('M d, Y') : 'N/A' }}</td><td>@include('accountant.partials.status-badge',['status'=>$i['status']])</td><td><a href="{{ route($i['route'],$i['param']) }}" class="btn btn-sm btn-secondary">View impact</a></td></tr>
@empty<tr><td colspan="9"></td></tr>@endforelse
</tbody></table></div></div>
@endsection
