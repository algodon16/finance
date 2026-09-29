@extends('layouts.app')
@section('title', isset($record->id) ? 'Revise Asset' : 'Prepare Asset')
@section('content')
<div class="page-header"><div><h2>{{ isset($record->id) ? 'Revise Asset' : 'Prepare Asset' }}</h2><p class="page-subtitle">Acquisition info, cost, purchase date, useful life. Depreciation method: straight-line.</p></div><a href="{{ route('accountant.assets.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ isset($record->id) ? route('accountant.assets.update',$record) : route('accountant.assets.store') }}" enctype="multipart/form-data">
@csrf @if(isset($record->id)) @method('PUT') @endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Asset</h3>
<div class="form-group"><label>Asset Name</label><input type="text" name="asset_name" class="form-control" value="{{ old('asset_name',$record->asset_name) }}" required></div>
<div class="form-group"><label>Category</label><input type="text" name="asset_category" class="form-control" value="{{ old('asset_category',$record->asset_category) }}" required></div>
<div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" class="form-control" value="{{ old('serial_number',$record->serial_number) }}"></div>
<div class="form-group"><label>Acquisition Date</label><input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date',optional($record->acquisition_date)->format('Y-m-d')) }}" required></div>
<div class="form-group"><label>Acquisition Cost</label><input type="number" step="0.01" min="0.01" name="acquisition_cost" class="form-control" value="{{ old('acquisition_cost',$record->acquisition_cost) }}" required></div>
<div class="form-group"><label>Salvage Value</label><input type="number" step="0.01" min="0" name="salvage_value" class="form-control" value="{{ old('salvage_value',$record->salvage_value ?? 0) }}" required></div>
<div class="form-group"><label>Useful Life (years)</label><input type="number" min="1" max="100" name="useful_life_years" class="form-control" value="{{ old('useful_life_years',$record->useful_life_years) }}" required></div>
</div>
<div class="dashboard-card"><h3>Custody &amp; Documents</h3>
<div class="form-group"><label>Location</label><input type="text" name="location" class="form-control" value="{{ old('location',$record->location) }}"></div>
<div class="form-group"><label>Department</label><input type="text" name="department" class="form-control" value="{{ old('department',$record->department) }}"></div>
<div class="form-group"><label>Custodian</label><input type="text" name="custodian" class="form-control" value="{{ old('custodian',$record->custodian) }}"></div>
<div class="form-group"><label>Remarks</label><textarea name="remarks" class="form-control" rows="3">{{ old('remarks',$record->remarks) }}</textarea></div>
<div class="form-group"><label>Supporting Document</label><input type="file" name="supporting_document" class="form-control"></div>
</div>
</div>
<div style="margin-top:12px;display:flex;gap:8px;"><button name="action" value="draft" class="btn btn-secondary">Save Draft</button>@if(!isset($record->id))<button name="action" value="submit" class="btn btn-primary">Save &amp; Submit for Approval</button>@else<button class="btn btn-primary">Save Revision as Draft</button>@endif</div>
</form>
@endsection
