@extends('layouts.app')
@section('title', 'Reconciliation Details')
@section('content')
<h2>{{ $record->reference_number }}</h2>
<p class="page-subtitle">Status: <span class="badge badge-gray">{{ $record->status }}</span> | Variance = Actual − System</p>
<div class="dashboard-card"><p><strong>Date:</strong> {{ optional($record->reconciliation_date)->format('M d, Y') }}</p><p><strong>System:</strong> ₱{{ number_format($record->system_amount,2) }} | <strong>Actual:</strong> ₱{{ number_format($record->actual_amount,2) }} | <strong>Variance:</strong> ₱{{ number_format($record->variance,2) }}</p><p><strong>Notes:</strong> {{ $record->notes }}</p>@if($record->supporting_document)<p><a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank">View document</a></p>@endif
@if($record->admin_remarks)<p><strong>Admin remarks:</strong> {{ $record->admin_remarks }}</p>@endif</div>
<div class="fms-actions" style="margin-top:12px;">
@if(!in_array($record->status,['submitted','reviewed']))<a href="{{ route('accountant.reconciliation-records.edit',$record) }}" class="btn btn-secondary">Edit</a><form method="POST" action="{{ route('accountant.reconciliation-records.submit',$record) }}">@csrf<button class="btn btn-primary">Submit for Admin Review</button></form>@endif
<a href="{{ route('accountant.reconciliation-records.index') }}" class="btn btn-secondary">Back</a></div>
@endsection
