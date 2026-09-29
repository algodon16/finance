@extends('layouts.app')
@section('title', 'Rejected / For Revision')
@section('content')
<div class="page-header"><div><h2>Rejected / For Revision</h2><p class="page-subtitle">Returned by admin. Revision preserves original history and bumps revision number.</p></div></div>
<div class="dashboard-card"><div class="table-responsive"><table class="table"><thead><tr><th>Reference No.</th><th>Type</th><th>Amount</th><th>Admin Decision</th><th>Admin Remarks</th><th>Date Returned</th><th>Action</th></tr></thead><tbody>
@forelse($items as $i)<tr><td><strong>{{ $i['ref'] }}</strong></td><td>{{ $i['type'] }}<br><span class="summary-desc">{{ $i['desc'] }}</span></td><td>₱{{ number_format($i['amount'] ?? 0,2) }}</td><td>@include('accountant.partials.status-badge',['status'=>$i['status']])<br><span class="summary-desc">{{ $i['reason'] ?? '—' }}</span></td><td>{{ $i['remarks'] ?? '—' }}</td><td>{{ $i['reviewed_at'] ? \Carbon\Carbon::parse($i['reviewed_at'])->format('M d, Y') : 'N/A' }}</td><td><a href="{{ route($i['route'],$i['param']) }}" class="btn btn-sm btn-primary">View / Edit / Resubmit</a></td></tr>
@empty<tr><td colspan="7"></td></tr>@endforelse
</tbody></table></div></div>
@endsection
