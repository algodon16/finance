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
        <div class="form-group"><label>Budget ID <span style="color:#64748b;">— i-type ang ID (hal. 25), kusang lalabas ang budget</span></label>
            <div style="display:flex;gap:8px;max-width:340px;"><input type="text" id="exp_budget_id" class="form-control" placeholder="Budget ID…" autocomplete="off" value="{{ old('budget_plan_id', $record->budget_plan_id) }}"><button type="button" id="exp_budget_load" class="btn btn-secondary">Load</button></div>
            <small id="exp-budget-msg" style="color:#64748b;"></small></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Budget Plan</label><select name="budget_plan_id" id="exp_budget_select" class="form-control"><option value="">None</option>@foreach($budgets as $b)<option value="{{ $b->id }}" {{ old('budget_plan_id', $record->budget_plan_id) == $b->id ? 'selected' : '' }}>#{{ $b->id }} — {{ $b->budget_name }} (Rem ₱{{ number_format($b->remaining_amount,2) }})</option>@endforeach</select>
            <div id="exp-budget-preview" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin-top:8px;display:none;font-size:0.85rem;"><strong id="ebp-label">—</strong><br><span id="ebp-lines"></span></div></div>
            <div class="form-group"><label>Fund</label><select name="fund_id" class="form-control"><option value="">None</option>@foreach($funds as $f)<option value="{{ $f->id }}" {{ old('fund_id', $record->fund_id) == $f->id ? 'selected' : '' }}>#{{ $f->id }} — {{ $f->fund_name }}</option>@endforeach</select></div>
        </div>
<script>
(function(){
  const previewUrl = "{{ route('admin.funds.budget-preview') }}";
  const idInput = document.getElementById('exp_budget_id');
  const loadBtn = document.getElementById('exp_budget_load');
  const msg = document.getElementById('exp-budget-msg');
  const sel = document.getElementById('exp_budget_select');
  const box = document.getElementById('exp-budget-preview');
  const label = document.getElementById('ebp-label');
  const lines = document.getElementById('ebp-lines');
  const peso = n => '₱' + (parseFloat(n)||0).toLocaleString('en-PH',{minimumFractionDigits:2});
  function renderBudget(d){
    box.style.display='block';
    label.textContent = 'Budget #' + d.id + ' — ' + d.label.replace(/^#\d+ — /,'');
    lines.innerHTML = 'Allocated: <strong>'+peso(d.allocated)+'</strong> | Used: <strong>'+peso(d.utilized)+'</strong> | Remaining: <strong>'+peso(d.remaining)+'</strong>';
    let matched = false;
    for(const o of sel.options){ if(o.value==String(d.id)){ sel.value=String(d.id); matched=true; break; } }
    msg.textContent = 'Nahanap: Budget #' + d.id + '.';
  }
  function syncFromSelect(){
    const o = sel.options[sel.selectedIndex];
    idInput.value = sel.value;
    if(!o || !o.value){ box.style.display='none'; msg.textContent=''; return; }
    box.style.display='block';
    label.textContent = 'Budget #' + o.value + ' — ' + o.text.split(' (')[0].replace(/^#\d+ — /,'');
    lines.innerHTML = '<span style="color:#64748b;">'+o.text.split(' (')[1].replace(/\)$/,'')+'</span>';
    msg.textContent='';
  }
  async function loadById(auto){
    const q = idInput.value.trim();
    if(!q){ if(!auto) msg.textContent='Maglagay muna ng Budget ID.'; return; }
    if(!auto) msg.textContent='Hinahanap…';
    try{
      const res = await fetch(previewUrl + '?q=' + encodeURIComponent(q), {headers:{'Accept':'application/json'}});
      if(res.status===404){ if(!auto) msg.textContent='Walang approved budget para sa "'+q+'".'; else box.style.display='none'; return; }
      if(!res.ok) throw 0;
      renderBudget(await res.json());
    }catch(e){ if(!auto) msg.textContent='Hindi ma-load. Subukang muli.'; }
  }
  loadBtn.addEventListener('click', ()=>loadById(false));
  idInput.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); loadById(false); } });
  let deb=null;
  idInput.addEventListener('input', ()=>{ clearTimeout(deb); const q=idInput.value.trim(); if(!q){msg.textContent='';return;} deb=setTimeout(()=>loadById(true),600); });
  sel.addEventListener('change', syncFromSelect);
  if(sel.value){ syncFromSelect(); }
})();
</script>
        <div class="form-group"><label>Supporting Document (PDF/JPG/PNG, max 5MB)</label><input type="file" name="supporting_document" class="form-control"></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $record->description) }}</textarea></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ $record->exists ? 'Update' : 'Record' }} Expense</button></div>
    </form>
</div>
@endsection
