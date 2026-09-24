@extends('layouts.admin')
@section('title', ($record->exists ? 'Edit' : 'New').' Procurement Request')
@section('content')
<div class="page-header">
    <h2>{{ $record->exists ? 'Edit Request' : 'New Procurement Request' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.procurement.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:760px;">
    <form method="POST" action="{{ $record->exists ? route('admin.procurement.update', $record) : route('admin.procurement.store') }}" enctype="multipart/form-data">
        @csrf
        @if($record->exists) @method('PUT') @endif
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Requesting Department <span class="required">*</span></label><input type="text" name="requesting_department" class="form-control" value="{{ old('requesting_department', $record->requesting_department) }}" required></div>
            @if(!$record->exists)
            <div class="form-group"><label>Status <span class="required">*</span></label>
                <select name="status" class="form-control" required>@foreach(['draft','submitted','under_review','approved','rejected','ordered','fulfilled','cancelled'] as $s)<option value="{{ $s }}" {{ old('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            @endif
        </div>
        <div class="form-group"><label>Item / Service <span class="required">*</span></label><textarea name="item_description" class="form-control" rows="2" required placeholder="e.g. Gala Uniform, NSTP Uniform, Instructional Materials, Learning Materials, Academic Supplies">{{ old('item_description', $record->item_description) }}</textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div class="form-group"><label>Quantity <span class="required">*</span></label><input type="number" min="1" name="quantity" class="form-control" value="{{ old('quantity', $record->quantity) }}" required></div>
            <div class="form-group"><label>Estimated Cost (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="estimated_cost" class="form-control" value="{{ old('estimated_cost', $record->estimated_cost) }}" required></div>
            <div class="form-group"><label>Supplier</label><input type="text" name="supplier" class="form-control" value="{{ old('supplier', $record->supplier) }}"></div>
        </div>
        <div class="form-group"><label>Justification <span class="required">*</span></label><textarea name="justification" class="form-control" rows="3" required>{{ old('justification', $record->justification) }}</textarea></div>
        <div class="form-group"><label>Supporting Document (PDF/JPG/PNG, max 5MB)</label><input type="file" name="supporting_document" class="form-control"></div>
        @if($record->exists)
        <div class="form-group"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $record->remarks) }}</textarea></div>
        @endif
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update' : 'Create' }} Request</button></div>
    </form>
</div>
@endsection
