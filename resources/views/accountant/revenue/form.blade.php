@extends('layouts.app')
@section('title', isset($record->id) ? 'Revise Revenue Transaction' : 'Record Revenue')
@section('content')
<div class="page-header"><div><h2>{{ isset($record->id) ? 'Revise Revenue Transaction' : 'Record Revenue' }}</h2><p class="page-subtitle">Draft saves as Pending; submit moves it to Under Review in Admin → Revenue Management (same record).</p></div><a href="{{ route('accountant.revenue.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ isset($record->id) ? route('accountant.revenue.update',$record) : route('accountant.revenue.store') }}" enctype="multipart/form-data">
@csrf @if(isset($record->id)) @method('PUT') @endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Transaction</h3>
<div class="form-group"><label>Student</label><select name="student_id" class="form-control" required><option value="">Select student</option>@foreach($students as $s)<option value="{{ $s->id }}" {{ old('student_id',$record->student_id)==$s->id?'selected':'' }}>{{ $s->student_number }} — {{ $s->full_name }}</option>@endforeach</select></div>
<div class="form-group"><label>Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount',$record->amount) }}" required></div>
<div class="form-group"><label>Payment Method</label><select name="payment_method" class="form-control" required>@foreach(['cash','bank_transfer','gcash','maya','other'] as $m)<option value="{{ $m }}" {{ old('payment_method',$record->payment_method)===$m?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$m)) }}</option>@endforeach</select></div>
<div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date',optional($record->payment_date)->format('Y-m-d')) }}" required></div>
</div>
<div class="dashboard-card"><h3>Details</h3>
<div class="form-group"><label>Fee Category</label><input type="text" name="fee_category" class="form-control" value="{{ old('fee_category',$record->fee_category) }}"></div>
<div class="form-group"><label>Reference Number</label><input type="text" name="reference_number" class="form-control" value="{{ old('reference_number',$record->reference_number) }}"></div>
<div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description',$record->description) }}</textarea></div>
<div class="form-group"><label>Proof of Payment (pdf/jpg/png)</label><input type="file" name="supporting_document" class="form-control"></div>
</div>
</div>
<div style="margin-top:12px;display:flex;gap:8px;"><button name="action" value="draft" class="btn btn-secondary">Save Draft</button>@if(!isset($record->id))<button name="action" value="submit" class="btn btn-primary">Save &amp; Submit for Approval</button>@else<button class="btn btn-primary">Save Revision as Draft</button>@endif</div>
</form>
@endsection
