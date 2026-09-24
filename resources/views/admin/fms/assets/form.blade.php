@extends('layouts.admin')
@section('title', ($record->exists ? 'Edit' : 'Register').' Asset')
@section('content')
<div class="page-header">
    <h2>{{ $record->exists ? 'Edit Asset' : 'Register Asset' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.assets.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:760px;">
    <form method="POST" action="{{ $record->exists ? route('admin.assets.update', $record) : route('admin.assets.store') }}">
        @csrf
        @if($record->exists) @method('PUT') @endif
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Asset Name <span class="required">*</span></label><input type="text" name="asset_name" class="form-control" value="{{ old('asset_name', $record->asset_name) }}" required></div>
            <div class="form-group"><label>Asset Category <span class="required">*</span></label>
                <select name="asset_category" class="form-control" required>
                    @foreach(['Computer Equipment','Furniture','Vehicles','Laboratory Equipment','Office Equipment','Buildings','Library Materials','Others'] as $c)<option {{ old('asset_category', $record->asset_category) === $c ? 'selected' : '' }}>{{ $c }}</option>@endforeach
                </select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $record->serial_number) }}"></div>
            <div class="form-group"><label>Acquisition Date <span class="required">*</span></label><input type="date" name="acquisition_date" class="form-control" value="{{ old('acquisition_date', optional($record->acquisition_date)->format('Y-m-d')) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div class="form-group"><label>Acquisition Cost (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="acquisition_cost" class="form-control" value="{{ old('acquisition_cost', $record->acquisition_cost) }}" required></div>
            <div class="form-group"><label>Salvage Value (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0" name="salvage_value" class="form-control" value="{{ old('salvage_value', $record->salvage_value ?? 0) }}" required></div>
            <div class="form-group"><label>Useful Life (Years) <span class="required">*</span></label><input type="number" min="1" max="100" name="useful_life_years" class="form-control" value="{{ old('useful_life_years', $record->useful_life_years) }}" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div class="form-group"><label>Location</label><input type="text" name="location" class="form-control" value="{{ old('location', $record->location) }}"></div>
            <div class="form-group"><label>Department</label><input type="text" name="department" class="form-control" value="{{ old('department', $record->department) }}"></div>
            <div class="form-group"><label>Custodian</label><input type="text" name="custodian" class="form-control" value="{{ old('custodian', $record->custodian) }}"></div>
        </div>
        <div class="form-group"><label>Asset Status <span class="required">*</span></label>
            <select name="asset_status" class="form-control" required>@foreach(['active','maintenance','disposed','lost','retired'] as $s)<option value="{{ $s }}" {{ old('asset_status', $record->asset_status ?? 'active') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
        <div class="form-group"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $record->remarks) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update' : 'Register' }} Asset</button></div>
    </form>
</div>
@endsection
