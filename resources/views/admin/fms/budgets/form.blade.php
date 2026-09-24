@extends('layouts.admin')
@section('title', ($plan->exists ? 'Edit' : 'Create').' Budget Plan')
@section('content')
<div class="page-header">
    <h2>{{ $plan->exists ? 'Edit Budget Plan' : 'Create Budget Plan' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.budgets.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:760px;">
    <form method="POST" action="{{ $plan->exists ? route('admin.budgets.update', $plan) : route('admin.budgets.store') }}">
        @csrf
        @if($plan->exists) @method('PUT') @endif
        <div class="form-group"><label>Budget Name <span class="required">*</span></label><input type="text" name="budget_name" class="form-control" value="{{ old('budget_name', $plan->budget_name) }}" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Fiscal Year <span class="required">*</span></label><input type="text" name="fiscal_year" class="form-control" placeholder="2026-2027" value="{{ old('fiscal_year', $plan->fiscal_year) }}" required></div>
            <div class="form-group"><label>Department</label><input type="text" name="department" class="form-control" value="{{ old('department', $plan->department) }}"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Budget Category <span class="required">*</span></label>
                <select name="budget_category" class="form-control" required>
                    @foreach(['Academic','Operational','Scholarship','Department','Campus','Special Project','Emergency'] as $c)
                        <option value="{{ $c }}" {{ old('budget_category', $plan->budget_category) === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select></div>
            <div class="form-group"><label>Allocated Amount (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="allocated_amount" class="form-control" value="{{ old('allocated_amount', $plan->allocated_amount) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Start Date <span class="required">*</span></label><input type="date" name="start_date" class="form-control" value="{{ old('start_date', optional($plan->start_date)->format('Y-m-d')) }}" required></div>
            <div class="form-group"><label>End Date <span class="required">*</span></label><input type="date" name="end_date" class="form-control" value="{{ old('end_date', optional($plan->end_date)->format('Y-m-d')) }}" required></div>
        </div>
        <div class="form-group"><label>Status <span class="required">*</span></label>
            <select name="status" class="form-control" required>
                @foreach(['active','draft','inactive','closed'] as $s)
                    <option value="{{ $s }}" {{ old('status', $plan->status ?? 'active') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $plan->description) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $plan->exists ? 'Update' : 'Create' }} Budget Plan</button></div>
    </form>
</div>
@endsection
