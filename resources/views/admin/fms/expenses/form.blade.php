@extends('layouts.admin')
@section('title', ($record->exists ? 'Edit' : 'Record').' Expense')
@section('content')
<div class="page-header">
    <h2>{{ $record->exists ? 'Edit Expense' : 'Record Expense' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.expenses.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:760px;">
    <form method="POST" action="{{ $record->exists ? route('admin.expenses.update', $record) : route('admin.expenses.store') }}" enctype="multipart/form-data">
        @csrf
        @if($record->exists) @method('PUT') @endif
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Reference Number <span class="required">*</span></label><input type="text" name="reference_number" class="form-control" value="{{ old('reference_number', $record->reference_number) }}" required></div>
            <div class="form-group"><label>Expense Category <span class="required">*</span></label>
                <select name="expense_category" class="form-control" required>
                    @foreach(['Salaries','Utilities','Supplies','Maintenance','Equipment','Transportation','Events','Scholarship Disbursement','Others'] as $c)<option {{ old('expense_category', $record->expense_category) === $c ? 'selected' : '' }}>{{ $c }}</option>@endforeach
                </select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Department</label><input type="text" name="department" class="form-control" value="{{ old('department', $record->department) }}"></div>
            <div class="form-group"><label>Payee <span class="required">*</span></label><input type="text" name="payee" class="form-control" value="{{ old('payee', $record->payee) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Amount (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', $record->amount) }}" required></div>
            <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', optional($record->expense_date)->format('Y-m-d') ?? today()->toDateString()) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Budget Plan</label><select name="budget_plan_id" class="form-control"><option value="">None</option>@foreach($budgets as $b)<option value="{{ $b->id }}" {{ old('budget_plan_id', $record->budget_plan_id) == $b->id ? 'selected' : '' }}>{{ $b->budget_name }}</option>@endforeach</select></div>
            <div class="form-group"><label>Fund</label><select name="fund_id" class="form-control"><option value="">None</option>@foreach($funds as $f)<option value="{{ $f->id }}" {{ old('fund_id', $record->fund_id) == $f->id ? 'selected' : '' }}>{{ $f->fund_name }}</option>@endforeach</select></div>
        </div>
        <div class="form-group"><label>Supporting Document (PDF/JPG/PNG, max 5MB)</label><input type="file" name="supporting_document" class="form-control"></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $record->description) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update' : 'Record' }} Expense</button></div>
    </form>
</div>
@endsection
