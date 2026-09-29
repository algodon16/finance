@extends('layouts.app')
@section('title', isset($plan->id) ? 'Edit Budget Plan' : 'Create Budget Plan')
@section('content')
<style>
.bp-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
.bp-info-grid .form-group{margin-bottom:0;}
.bp-period{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.bp-date-row{grid-column:1/-1;display:grid;grid-template-columns:repeat(2,180px);gap:12px;justify-content:start;}
.bp-date-row .form-control{max-width:180px;padding:6px 8px;font-size:.85rem;}
.bp-total-bar{display:flex;justify-content:space-between;align-items:center;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-top:12px;flex-wrap:wrap;gap:8px;}
.bp-total-bar strong{font-size:1.25rem;}
@media(max-width:900px){.bp-info-grid{grid-template-columns:1fr;}.bp-date-row{grid-template-columns:1fr;}.bp-date-row .form-control{max-width:100%;}}
</style>

<div class="page-header"><div><h2>{{ isset($plan->id) ? 'Edit Budget Plan' : 'Create Budget Plan' }}</h2><p class="page-subtitle">Fill in Information, enter Total Proposed, then click DONE. Line items are now prepared under Procurement and Financial Requests.</p></div><a href="{{ route('accountant.budgets.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<form method="POST" action="{{ isset($plan->id) ? route('accountant.budgets.update',$plan) : route('accountant.budgets.store') }}" enctype="multipart/form-data" id="budgetPlanForm">
@csrf @if(isset($plan->id)) @method('PUT') @endif

{{-- ============ INFORMATION ============ --}}
<div class="dashboard-card">
<h3>Information</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Budget Purpose</label><input type="text" name="budget_name" class="form-control" value="{{ old('budget_name',$plan->budget_name) }}" placeholder="e.g. Academic Operations 2026-2027" required></div>
<div class="form-group"><label>Department (Academic Course)</label><input type="text" name="department" class="form-control" list="deptList" value="{{ old('department',$plan->department) }}" placeholder="e.g. College of Computer Studies" required><datalist id="deptList"><option value="College of Computer Studies"></option><option value="College of Business Administration"></option><option value="College of Hospitality Management"></option><option value="College of Education"></option><option value="College of Criminology"></option><option value="Senior High School"></option><option value="Academic Affairs"></option></datalist></div>
<div class="form-group"><label>Academic Year</label><input type="text" name="fiscal_year" class="form-control" value="{{ old('fiscal_year',$plan->fiscal_year) }}" placeholder="2026-2027" required></div>
<div class="form-group"><label>Program / Project</label><input type="text" name="budget_category" class="form-control" list="progList" value="{{ old('budget_category',$plan->budget_category) }}" placeholder="e.g. BSIT Program / Laboratory Upgrade" required><datalist id="progList"><option value="BSIT Program"></option><option value="BSCS Program"></option><option value="BSHM Program"></option><option value="BSBA Program"></option><option value="BEEd / BSEd Program"></option><option value="Laboratory Upgrade"></option><option value="Library Resources"></option><option value="Campus Maintenance"></option></datalist></div>
<div class="bp-date-row">
<div class="form-group"><label>Budget Period Start</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date',optional($plan->start_date)->format('Y-m-d')) }}" required></div>
<div class="form-group"><label>Budget Period End</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date',optional($plan->end_date)->format('Y-m-d')) }}" required></div>
</div>
<div class="form-group"><label>Fund Source</label><select name="funding_source" class="form-control" required><option value="">Select fund source</option>@foreach(($funds ?? []) as $f)<option value="{{ $f->fund_name }}" {{ old('funding_source',$plan->funding_source)===$f->fund_name?'selected':'' }}>{{ $f->fund_name }} (Avail ₱{{ number_format($f->available_amount,2) }})</option>@endforeach<option value="General Fund" {{ old('funding_source',$plan->funding_source)==='General Fund'?'selected':'' }}>General Fund</option><option value="Tuition Fees" {{ old('funding_source',$plan->funding_source)==='Tuition Fees'?'selected':'' }}>Tuition Fees</option><option value="Special Project Fund" {{ old('funding_source',$plan->funding_source)==='Special Project Fund'?'selected':'' }}>Special Project Fund</option></select></div>
</div>
<div class="form-group" style="margin-top:8px;"><label>Total Proposed Amount (₱)</label><input type="number" step="0.01" min="0.01" name="allocated_amount" id="allocatedAmount" class="form-control" value="{{ old('allocated_amount',$plan->allocated_amount) }}" placeholder="e.g. 150000.00" required><p class="summary-desc">Direktang total — hindi na galing sa line items. Ang item breakdown ay sa Procurement and Financial Requests na ilalagay.</p></div>
<div class="form-group" style="margin-top:8px;"><label>Supporting Document (pdf/jpg/png, 5MB, optional)</label><input type="file" name="supporting_document" class="form-control">@if(!empty($plan->supporting_document))<p><a href="{{ asset('storage/'.$plan->supporting_document) }}" target="_blank">Current document</a></p>@endif</div>
</div>

{{-- ============ SUMMARY ============ --}}
<div class="dashboard-card" style="margin-top:16px;">
<h3>Summary</h3>
<div class="bp-total-bar"><div>Available Funds: <strong>₱{{ number_format($availableFunds ?? 0,2) }}</strong></div><div>Total Proposed: <strong id="totalPreview">₱{{ number_format((float) old('allocated_amount',$plan->allocated_amount ?? 0),2) }}</strong></div></div>
<p class="page-subtitle" style="margin-top:8px;">Need item breakdown? After saving the plan, go to <a href="{{ route('accountant.financial-requests.create') }}">Procurement and Financial Requests → Prepare Request</a> and add Budget Items there.</p>
</div>

<div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
<button name="action" value="draft" class="btn btn-secondary">Save Draft</button>
<button name="action" value="submit" class="btn btn-primary" style="min-width:160px;">DONE</button>
</div>
@if(isset($plan->id))<p class="page-subtitle" style="text-align:right;margin-top:6px;">Saving a revision stores it as draft — submit for admin approval from the overview.</p>@endif
</form>

@push('scripts')<script>
function peso(n){return '₱'+(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}
function recalc(){
  const v=parseFloat(document.getElementById('allocatedAmount').value)||0;
  document.getElementById('totalPreview').textContent=peso(v);
}
document.getElementById('allocatedAmount').addEventListener('input',recalc);
recalc();
</script>@endpush
@endsection
