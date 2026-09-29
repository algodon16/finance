@extends('layouts.app')
@section('title', 'Pending Admin Approval')
@section('content')
<div class="page-header"><div><h2>Pending Admin Approval</h2><p class="page-subtitle">Submission queue. View / withdraw while pending. You cannot approve here.</p></div></div>
<div class="dashboard-card"><form method="GET" class="filter-form" style="align-items:flex-end;"><div class="form-group" style="min-width:200px;"><label>Type</label><select name="type" class="form-control"><option value="">All types</option>@foreach(['budget'=>'Budget Plan','allocation'=>'Fund Allocation','expense'=>'Expense Proposal','payable'=>'Accounts Payable','request'=>'Financial Request','reconciliation'=>'Reconciliation'] as $k=>$l)<option value="{{ $k }}" {{ ($type ?? '')===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div><div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.submissions.pending') }}" class="btn btn-secondary">Clear</a></div></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Reference No.</th><th>Type</th><th>Description</th><th>Amount</th><th>Submitted By</th><th>Date Submitted</th><th>Status</th><th>Days</th><th>Action</th></tr></thead><tbody>
@forelse($items as $i)<tr><td><strong>{{ $i['ref'] }}</strong></td><td>{{ $i['type'] }}</td><td>{{ $i['desc'] }}</td><td>₱{{ number_format($i['amount'] ?? 0,2) }}</td><td>{{ $i['by'] }}</td><td>{{ $i['submitted'] ? \Carbon\Carbon::parse($i['submitted'])->format('M d, Y h:i A') : 'N/A' }}</td><td>@include('accountant.partials.status-badge',['status'=>$i['status']])</td><td>{{ $i['days'] }}</td><td style="white-space:nowrap;"><a href="{{ route($i['route'],$i['param']) }}" class="btn btn-sm btn-secondary">View / Details</a></td></tr>
@empty<tr><td colspan="9"></td></tr>@endforelse
</tbody></table></div>
<p class="summary-desc">Approval timeline per record is visible on its details page. Withdraw returns the record to draft.</p>
</div>
@endsection
