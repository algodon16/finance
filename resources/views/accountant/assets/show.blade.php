@extends('layouts.app')
@section('title', 'Asset Details')
@section('content')
<div class="page-header"><div><h2>{{ $record->asset_code }} — {{ $record->asset_name }}</h2><p class="page-subtitle">@include('accountant.partials.status-badge',['status'=>$record->approval_status ?? 'draft']) | Rev {{ $record->revision_number ?? 0 }} | Same record as Admin.</p></div><a href="{{ route('accountant.assets.index') }}" class="btn btn-secondary">Back</a></div>
@if($record->rejection_reason)<div class="alert alert-error"><strong>Rejection reason:</strong> {{ $record->rejection_reason }}<br><strong>Admin remarks:</strong> {{ $record->admin_remarks ?? '—' }}</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Asset</h3>
<p><strong>Category:</strong> {{ $record->asset_category }} | <strong>Serial:</strong> {{ $record->serial_number ?? '—' }}</p>
<p><strong>Acquired:</strong> {{ optional($record->acquisition_date)->format('M d, Y') }} | <strong>Cost:</strong> ₱{{ number_format($record->acquisition_cost,2) }} | <strong>Salvage:</strong> ₱{{ number_format($record->salvage_value,2) }}</p>
<p><strong>Useful life:</strong> {{ $record->useful_life_years }} yrs | <strong>Location:</strong> {{ $record->location ?? '—' }} | <strong>Custodian:</strong> {{ $record->custodian ?? '—' }}</p>
@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View document</a></p>@endif
</div>
<div class="dashboard-card"><h3>Depreciation (straight-line, approved records)</h3>
<p>Annual: <strong>₱{{ number_format($record->annual_depreciation,2) }}</strong> | Monthly: <strong>₱{{ number_format($record->monthly_depreciation,2) }}</strong></p>
<p>Accumulated: <strong>₱{{ number_format($record->accumulated_depreciation,2) }}</strong> | Book value: <strong>₱{{ number_format($record->book_value,2) }}</strong></p>
<p>Remaining life: <strong>{{ $record->remaining_life }} yrs</strong></p>
<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
@if(in_array($record->approval_status ?? 'draft',['draft','for_revision','revision','rejected','cancelled']))<a href="{{ route('accountant.assets.edit',$record) }}" class="btn btn-secondary">Edit / Revise</a><form method="POST" action="{{ route('accountant.assets.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Approval</button></form>@endif
@if(in_array($record->approval_status,['submitted','under_review']))<form method="POST" action="{{ route('accountant.assets.cancel',$record) }}">@csrf<button class="btn btn-secondary">Withdraw</button></form>@endif
</div></div>
</div>
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@endsection
