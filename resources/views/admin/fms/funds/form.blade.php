@extends('layouts.admin')
@section('title', ($fund->exists ? 'Edit' : 'Create').' Fund')
@section('content')
<div class="page-header">
    <h2>{{ $fund->exists ? 'Edit Fund' : 'Create Fund' }}</h2>
    <a class="btn btn-secondary" href="{{ route('admin.funds.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:640px;">
    <form method="POST" action="{{ $fund->exists ? route('admin.funds.update', $fund) : route('admin.funds.store') }}">
        @csrf
        @if($fund->exists) @method('PUT') @endif
        <div class="form-group"><label>Fund Name <span class="required">*</span></label><input type="text" name="fund_name" class="form-control" value="{{ old('fund_name', $fund->fund_name) }}" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Fund Source</label><input type="text" name="fund_source" class="form-control" value="{{ old('fund_source', $fund->fund_source) }}"></div>
            <div class="form-group"><label>Fund Type <span class="required">*</span></label>
                <input type="text" name="fund_type" id="fund_type_input" class="form-control" list="fund-type-list" value="{{ old('fund_type', $fund->fund_type ?? 'general') }}" placeholder="e.g. general" required autocomplete="off">
                <datalist id="fund-type-list">
                    @foreach(['general'=>'General Fund','academic'=>'Academic Fund','scholarship'=>'Scholarship Fund','department'=>'Department Fund','campus'=>'Campus Fund','emergency'=>'Emergency Reserve','special'=>'Special Project Fund'] as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </datalist>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                    @foreach(['general'=>'General','academic'=>'Academic','scholarship'=>'Scholarship','department'=>'Department','campus'=>'Campus','emergency'=>'Emergency','special'=>'Special'] as $k=>$v)<button type="button" class="btn btn-sm btn-secondary fund-type-chip" data-val="{{ $k }}">{{ $v }}</button>@endforeach
                </div></div>
        </div>
        <script>
        (function(){
          const inp = document.getElementById('fund_type_input');
          if(!inp) return;
          document.querySelectorAll('.fund-type-chip').forEach(ch=>{
            ch.addEventListener('click',()=>{ inp.value = ch.dataset.val; inp.focus(); });
          });
        })();
        </script>
        @if(!$fund->exists)
        <div class="form-group"><label>Initial Balance (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0" name="initial_balance" class="form-control" value="{{ old('initial_balance', 0) }}" required></div>
        @endif
        <div class="form-group"><label>Status <span class="required">*</span></label>
            <select name="status" class="form-control" required><option value="active" {{ old('status', $fund->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option><option value="inactive" {{ old('status', $fund->status) === 'inactive' ? 'selected' : '' }}>Inactive</option></select></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $fund->description) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $fund->exists ? 'Update' : 'Create' }} Fund</button></div>
    </form>
</div>
@endsection