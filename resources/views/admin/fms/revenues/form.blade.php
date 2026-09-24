@extends('layouts.admin')
@section('title', ($record->exists ? 'Edit' : 'Record').' Payment')
@section('content')
<div class="page-header">
    <h2>{{ $record->exists ? 'Edit Payment' : 'Record Incoming Payment' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.revenues.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:760px;">
    <form method="POST" action="{{ $record->exists ? route('admin.revenues.update', $record) : route('admin.revenues.store') }}">
        @csrf
        @if($record->exists) @method('PUT') @endif
        @if(!$record->exists)
        <div class="form-group"><label>Student Reference <span class="required">*</span></label>
            <select name="student_id" class="form-control" required>
                <option value="">Select student</option>
                @foreach($students as $s)<option value="{{ $s->id }}" {{ old('student_id', $record->student_id) == $s->id ? 'selected' : '' }}>{{ $s->student_number }} - {{ $s->full_name }}</option>@endforeach
            </select></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Transaction Number</label><input type="text" name="transaction_number" class="form-control" placeholder="Auto-generated if blank" value="{{ old('transaction_number') }}"></div>
            <div class="form-group"><label>Initial Status</label><select name="payment_status" class="form-control"><option value="pending">Pending</option><option value="verified">Verified</option><option value="reconciled">Reconciled</option></select></div>
        </div>
        @endif
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Payment Date <span class="required">*</span></label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', optional($record->payment_date)->format('Y-m-d') ?? today()->toDateString()) }}" required></div>
            <div class="form-group"><label>Payment Method <span class="required">*</span></label>
                <select name="payment_method" class="form-control" required>
                    @foreach(['Cash','GCash','Bank Transfer','Check','Online Payment'] as $m)<option {{ old('payment_method', $record->payment_method) === $m ? 'selected' : '' }}>{{ $m }}</option>@endforeach
                </select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Amount (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $record->amount) }}" required></div>
            <div class="form-group"><label>Fee Category</label>
                <select name="fee_category" class="form-control">
                    <option value="">Select category</option>
                    @foreach(['Tuition Fee','Miscellaneous Fee','Laboratory Fee','Library Fee','NSTP Fee','Uniform Fee','Instructional Materials','Others'] as $c)<option {{ old('fee_category', $record->fee_category) === $c ? 'selected' : '' }}>{{ $c }}</option>@endforeach
                </select></div>
        </div>
        <div class="form-group"><label>Reference Number</label><input type="text" name="reference_number" class="form-control" value="{{ old('reference_number', $record->reference_number) }}"></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $record->description) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update' : 'Record' }} Payment</button></div>
    </form>
</div>
@endsection
