@extends('layouts.app')
@section('title', 'Allocation Details')
@section('content')
<div class="page-header"><div><h2>Allocation #{{ $record->id }} — {{ $record->allocated_to }}</h2><p class="page-subtitle">Rev {{ $record->revision_number ?? 0 }} | @include('accountant.partials.status-badge',['status'=>$record->status])</p></div><a href="{{ route('accountant.fund-allocations.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->rejection_reason)<div class="alert alert-error"><strong>Admin decision:</strong> {{ $record->rejection_reason }}<br><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Allocation</h3><p><strong>Fund:</strong> {{ $record->fund->fund_name ?? 'N/A' }} (Avail ₱{{ number_format($record->fund->available_amount ?? 0,2) }})</p><p><strong>Budget ID:</strong> @if($record->budget_plan_id)<strong>#{{ $record->budget_plan_id }}</strong> — {{ $record->budgetPlan->budget_name ?? '' }}@else — @endif</p><p><strong>Amount:</strong> ₱{{ number_format($record->amount,2) }} | <strong>Date:</strong> {{ optional($record->allocation_date)->format('M d, Y') }}</p><p><strong>Purpose:</strong> {{ $record->purpose }}</p><p><strong>Description:</strong> {{ $record->description }}</p>@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View document</a></p>@endif</div>
<div class="dashboard-card"><h3>Budget Utilization Panel</h3>
@if($util)<p>Approved Budget: <strong>₱{{ number_format($util['approved'],2) }}</strong></p><p>Already Allocated: <strong>₱{{ number_format($util['allocated'],2) }}</strong></p><p>Remaining Available: <strong>₱{{ number_format($util['remaining'],2) }}</strong></p><p>Proposed Allocation: <strong>₱{{ number_format($util['proposed'],2) }}</strong></p><p>Remaining After: <strong>₱{{ number_format($util['after'],2) }}</strong></p>@else<p class="summary-desc">No linked budget — allocation draws from fund availability only.</p>@endif
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
<p class="summary-desc">Read-only record. New allocations are created automatically from approved Budget Requests.</p>
@if(in_array($record->status,['submitted','under_review']))<form method="POST" action="{{ route('accountant.fund-allocations.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div></div>
</div>
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@endsection
