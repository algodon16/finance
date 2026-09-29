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
            <div class="form-group"><label>Fund Type <span class="required">*</span> <span style="color:#64748b;">— pindutin o i-type, isahang set lang</span></label>
                <input type="text" name="fund_type" id="fund_type_input" class="form-control" list="fund-type-list" value="{{ old('fund_type', $fund->fund_type ?? 'general') }}" placeholder="hal. general" required autocomplete="off">
                <datalist id="fund-type-list">
                    @foreach(['general'=>'General Fund','academic'=>'Academic Fund','scholarship'=>'Scholarship Fund','department'=>'Department Fund','campus'=>'Campus Fund','emergency'=>'Emergency Reserve','special'=>'Special Project Fund'] as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </datalist>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                    @foreach(['general'=>'General','academic'=>'Academic','scholarship'=>'Scholarship','department'=>'Department','campus'=>'Campus','emergency'=>'Emergency','special'=>'Special'] as $k=>$v)<button type="button" class="btn btn-sm btn-secondary fund-type-chip" data-val="{{ $k }}">{{ $v }}</button>@endforeach
                </div></div>
<script>
(function(){
  const inp = document.getElementById('fund_type_input');
  if(!inp) return;
  document.querySelectorAll('.fund-type-chip').forEach(ch=>{
    ch.addEventListener('click',()=>{ inp.value = ch.dataset.val; inp.focus(); });
  });
})();
</script>
        </div>
        <div class="form-group"><label>Budget ID <span style="color:#64748b;">— i-type ang ID (hal. 25) para makita ang budget bago maglagay ng balance</span></label>
            <div style="display:flex;gap:8px;max-width:340px;"><input type="text" id="fund_budget_id" class="form-control" placeholder="Budget ID…" autocomplete="off"><button type="button" id="fund_budget_load" class="btn btn-secondary">Load</button></div>
            <small id="fund-budget-msg" style="color:#64748b;"></small>
            <div id="fund-budget-preview" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin-top:8px;display:none;font-size:0.85rem;"><strong id="fbp-label">—</strong><br><span id="fbp-lines"></span></div></div>
        @if(!$fund->exists)
        <div class="form-group"><label>Initial Balance (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0" name="initial_balance" id="fund_initial_balance" class="form-control" value="{{ old('initial_balance', 0) }}" required></div>
        @endif
<script>
(function(){
  const previewUrl = "{{ route('admin.funds.budget-preview') }}";
  const idInput = document.getElementById('fund_budget_id');
  const loadBtn = document.getElementById('fund_budget_load');
  const msg = document.getElementById('fund-budget-msg');
  const box = document.getElementById('fund-budget-preview');
  const label = document.getElementById('fbp-label');
  const lines = document.getElementById('fbp-lines');
  const bal = document.getElementById('fund_initial_balance');
  const peso = n => '₱' + (parseFloat(n)||0).toLocaleString('en-PH',{minimumFractionDigits:2});
  async function loadById(auto){
    const q = idInput.value.trim();
    if(!q){ if(!auto) msg.textContent='Maglagay muna ng Budget ID.'; return; }
    if(!auto) msg.textContent='Hinahanap…';
    try{
      const res = await fetch(previewUrl + '?q=' + encodeURIComponent(q), {headers:{'Accept':'application/json'}});
      if(res.status===404){ if(!auto) msg.textContent='Walang approved budget para sa "'+q+'".'; else box.style.display='none'; return; }
      if(!res.ok) throw 0;
      const d = await res.json();
      box.style.display='block';
      label.textContent = 'Budget #' + d.id + ' — ' + d.label.replace(/^#\d+ — /,'');
      lines.innerHTML = 'Allocated: <strong>'+peso(d.allocated)+'</strong> | Used: <strong>'+peso(d.utilized)+'</strong> | Remaining: <strong>'+peso(d.remaining)+'</strong><br><span style="color:#64748b;">'+(d.department||'')+' · '+(d.fiscal_year||'')+'</span>';
      msg.textContent = 'Nahanap: Budget #' + d.id + '. Puwedeng gawing basehan ng initial balance.';
      if(bal && !parseFloat(bal.value)) bal.value = d.remaining;
    }catch(e){ if(!auto) msg.textContent='Hindi ma-load. Subukang muli.'; }
  }
  loadBtn.addEventListener('click', ()=>loadById(false));
  idInput.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); loadById(false); } });
  let deb=null;
  idInput.addEventListener('input', ()=>{ clearTimeout(deb); const q=idInput.value.trim(); if(!q){msg.textContent='';return;} deb=setTimeout(()=>loadById(true),600); });
})();
</script>
        <div class="form-group"><label>Status <span class="required">*</span></label>
            <select name="status" class="form-control" required><option value="active" {{ old('status', $fund->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option><option value="inactive" {{ old('status', $fund->status) === 'inactive' ? 'selected' : '' }}>Inactive</option></select></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $fund->description) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $fund->exists ? 'Update' : 'Create' }} Fund</button></div>
    </form>
</div>
@endsection
