@extends('layouts.app')
@section('title', 'Prepare Reconciliation')
@section('content')
<h2>{{ isset($record->id) ? 'Edit Reconciliation' : 'Prepare Reconciliation' }}</h2>
<form method="POST" action="{{ isset($record->id) ? route('accountant.reconciliation-records.update',$record) : route('accountant.reconciliation-records.store') }}" enctype="multipart/form-data">
@csrf @if(isset($record->id)) @method('PUT') @endif
<div class="form-group"><label>Reconciliation Date</label><input type="date" name="reconciliation_date" class="form-control" value="{{ old('reconciliation_date',optional($record->reconciliation_date)->format('Y-m-d')) }}" required></div>
<div class="form-group"><label>Payment Method</label><input type="text" name="payment_method" class="form-control" value="{{ old('payment_method',$record->payment_method) }}"></div>
<div class="form-group"><label>System Amount</label><input type="number" step="0.01" min="0" name="system_amount" class="form-control" value="{{ old('system_amount',$record->system_amount) }}" required></div>
<div class="form-group"><label>Actual Amount</label><input type="number" step="0.01" min="0" name="actual_amount" class="form-control" value="{{ old('actual_amount',$record->actual_amount) }}" required></div>
<div class="form-group"><label>Reference</label><input type="text" name="reference" class="form-control" value="{{ old('reference',$record->reference) }}"></div>
<div class="form-group"><label>Payment ID (optional link)</label><input type="number" name="payment_id" class="form-control" value="{{ old('payment_id',$record->payment_id) }}"></div>
<div class="form-group"><label>Notes</label><textarea name="notes" class="form-control">{{ old('notes',$record->notes) }}</textarea></div>
<div class="form-group"><label>Supporting Document</label><input type="file" name="supporting_document" class="form-control"></div>
<button class="btn btn-primary">Save</button>
</form>
@endsection
