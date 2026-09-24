@extends('layouts.admin')
@section('title', ($record->exists ? 'Edit' : 'Record').' Payable')
@section('content')
<div class="page-header">
    <h2>{{ $record->exists ? 'Edit Payable' : 'Record Vendor Payable' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.payables.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:760px;">
    <form method="POST" action="{{ $record->exists ? route('admin.payables.update', $record) : route('admin.payables.store') }}" enctype="multipart/form-data">
        @csrf
        @if($record->exists) @method('PUT') @endif
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Vendor <span class="required">*</span></label><input type="text" name="vendor" class="form-control" value="{{ old('vendor', $record->vendor) }}" required></div>
            <div class="form-group"><label>Invoice Number <span class="required">*</span></label><input type="text" name="invoice_number" class="form-control" value="{{ old('invoice_number', $record->invoice_number) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div class="form-group"><label>Invoice Date <span class="required">*</span></label><input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', optional($record->invoice_date)->format('Y-m-d')) }}" required></div>
            <div class="form-group"><label>Due Date <span class="required">*</span></label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($record->due_date)->format('Y-m-d')) }}" required></div>
            <div class="form-group"><label>Amount (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $record->amount) }}" required></div>
        </div>
        <div class="form-group"><label>Payment Schedule</label><textarea name="payment_schedule" class="form-control" rows="2">{{ old('payment_schedule', $record->payment_schedule) }}</textarea></div>
        <div class="form-group"><label>Supporting Document (PDF/JPG/PNG, max 5MB)</label><input type="file" name="supporting_document" class="form-control"></div>
        <div class="form-group"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $record->remarks) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update' : 'Record' }} Payable</button></div>
    </form>
</div>
@endsection
