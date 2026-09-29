@extends('layouts.app')
@section('title', 'Prepare Expense Proposal')
@section('content')
<div class="page-header"><div><h2>{{ isset($record->id) ? 'Revise Proposal' : 'Create Expense Proposal' }}</h2><p class="page-subtitle">Link budget + allocation. Server validates Remaining Budget and allocation balance.</p></div><a href="{{ route('accountant.expenses.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ isset($record->id) ? route('accountant.expenses.update',$record) : route('accountant.expenses.store') }}" enctype="multipart/form-data">
@csrf @if(isset($record->id)) @method('PUT') @endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Proposal Details</h3>
<div class="form-group"><label>Reference auto-generated on create</label></div>
<div class="form-group"><label>Payee</label><input type="text" name="payee" class="form-control" value="{{ old('payee',$record->payee) }}" required></div>
<div class="form-group"><label>Expense Category</label><input type="text" name="expense_category" class="form-control" value="{{ old('expense_category',$record->expense_category) }}" required></div>
<div class="form-group"><label>Department</label><input type="text" name="department" class="form-control" value="{{ old('department',$record->department) }}" required></div>
<div class="form-group"><label>Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount',$record->amount) }}" required></div>
<div class="form-group"><label>Expense Date</label><input type="date" name="expense_date" class="form-control" value="{{ old('expense_date',optional($record->expense_date)->format('Y-m-d')) }}" required></div>
<div class="form-group"><label>Due / Proposed Payment Date</label><input type="date" name="proposed_payment_date" class="form-control" value="{{ old('proposed_payment_date',optional($record->proposed_payment_date)->format('Y-m-d')) }}"></div>
<div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" required>{{ old('description',$record->description) }}</textarea></div>
</div>
<div class="dashboard-card"><h3>Budget / Fund Source</h3>
<div class="form-group"><label>Budget (approved only)</label><select name="budget_plan_id" class="form-control"><option value="">— None —</option>@foreach($budgets as $b)<option value="{{ $b->id }}" {{ old('budget_plan_id',$record->budget_plan_id)==$b->id?'selected':'' }}>{{ $b->budget_name }} (Rem ₱{{ number_format($b->remaining_amount,2) }})</option>@endforeach</select></div>
<div class="form-group"><label>Related Allocation (approved)</label><select name="fund_allocation_id" class="form-control"><option value="">— None —</option>@foreach($allocations as $a)<option value="{{ $a->id }}" {{ old('fund_allocation_id',$record->fund_allocation_id)==$a->id?'selected':'' }}>#{{ $a->id }} {{ $a->allocated_to }} — ₱{{ number_format($a->amount,2) }}</option>@endforeach</select></div>
<div class="form-group"><label>Fund</label><select name="fund_id" class="form-control"><option value="">— None —</option>@foreach($funds as $f)<option value="{{ $f->id }}" {{ old('fund_id',$record->fund_id)==$f->id?'selected':'' }}>{{ $f->fund_name }}</option>@endforeach</select></div>
<div class="form-group"><label>Fund Source (text)</label><input type="text" name="fund_source" class="form-control" value="{{ old('fund_source',$record->fund_source) }}"></div>
<div class="form-group"><label>Justification</label><textarea name="justification" class="form-control" rows="2">{{ old('justification',$record->justification) }}</textarea></div>
<div class="form-group"><label>Supporting Document</label><input type="file" name="supporting_document" class="form-control"></div>
</div>
</div>
<div style="margin-top:12px;display:flex;gap:8px;"><button name="action" value="draft" class="btn btn-secondary">Save Draft</button>@if(!isset($record->id))<button name="action" value="submit" class="btn btn-primary">Save &amp; Submit for Admin Approval</button>@else<button class="btn btn-primary">Save Revision as Draft</button>@endif</div>
</form>
@endsection
