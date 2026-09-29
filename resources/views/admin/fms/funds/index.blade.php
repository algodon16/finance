@extends('layouts.admin')
@section('title', 'Fund Management and Allocation')
@section('content')
<div class="page-header">
    <h2>Fund Management and Allocation</h2>
    <a class="btn btn-primary" href="{{ route('admin.funds.create') }}">Create Fund</a>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Total Initial</h4><p class="val">P{{ number_format($totals['initial'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Current</h4><p class="val">P{{ number_format($totals['current'], 2) }}</p></div>
    <div class="fms-stat"><h4>Total Reserved</h4><p class="val">P{{ number_format($totals['reserved'], 2) }}</p></div>
    <div class="fms-stat"><h4>Available</h4><p class="val">P{{ number_format($totals['current'] - $totals['reserved'], 2) }}</p></div>
</div>

<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div><label style="font-size:0.8rem;">Search</label><br><input type="text" name="search" class="form-control" style="width:200px;" placeholder="Search fund" value="{{ request('search') }}"></div>
        <div><label style="font-size:0.8rem;">Type</label><br>
        <select name="fund_type" class="form-control" style="min-width:160px;">
            <option value="">All types</option>
            @foreach(['general' => 'General', 'academic' => 'Academic', 'scholarship' => 'Scholarship', 'department' => 'Department', 'campus' => 'Campus', 'emergency' => 'Emergency', 'special' => 'Special'] as $t => $label)
                <option value="{{ $t }}" {{ request('fund_type') === $t ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><button class="btn btn-secondary" type="submit">Filter</button> <a class="btn btn-secondary" href="{{ route('admin.funds.index') }}">Reset</a></div>
    </form>
</div>

<div class="fms-panel">
    <h3>Split Budget Across Fund Sources (auto-compute, editable)</h3>
    @if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px;">
        <div><label style="font-size:0.8rem;">Budget ID * <span style="color:#64748b;">— i-type, kusang lalabas</span></label><br>
        <div style="display:flex;gap:8px;"><input type="text" id="ms_budget_id" class="form-control" placeholder="hal. 25" autocomplete="off" style="width:130px;"><button type="button" id="ms_budget_load" class="btn btn-secondary">Load</button></div>
        <small id="ms-budget-msg" style="color:#64748b;"></small></div>
        <div><label style="font-size:0.8rem;">Allocated To (Dept / Program) *</label><br><input type="text" id="ms_target" class="form-control" placeholder="Department / Program" style="min-width:220px;"></div>
        <div><label style="font-size:0.8rem;">Date *</label><br><input type="date" id="ms_date" class="form-control" value="{{ today()->toDateString() }}"></div>
        <div><button type="button" id="ms_auto" class="btn btn-secondary">Auto-Split</button></div>
    </div>
    <div id="ms-budget-preview" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:12px;display:none;">
        <strong id="msbp-label">—</strong><br><span id="msbp-lines" style="font-size:0.85rem;"></span>
    </div>
    <form method="POST" action="{{ route('admin.funds.allocate-multi') }}" id="ms-form">
        @csrf
        <input type="hidden" name="budget_plan_id" id="ms_budget_hidden" value="">
        <input type="hidden" name="allocated_to" id="ms_target_hidden" value="">
        <input type="hidden" name="allocation_date" id="ms_date_hidden" value="{{ today()->toDateString() }}">
        <div style="overflow-x:auto;">
        <table class="fms-table"><thead><tr><th>Fund Source</th><th style="text-align:right;">Available</th><th style="text-align:right;">Amount (editable) *</th></tr></thead>
        <tbody id="ms-rows">
        @foreach($splitFunds ?? [] as $i => $f)
        <tr data-fund="{{ $f->id }}" data-avail="{{ (float)$f->available_amount }}">
            <td><strong>{{ $f->fund_name }}</strong><br><span style="color:#64748b;font-size:.8rem;">{{ ucfirst($f->fund_type) }}</span></td>
            <td style="text-align:right;" data-avail-cell>₱{{ number_format($f->available_amount,2) }}</td>
            <td style="text-align:right;"><input type="number" name="rows[{{ $i }}][amount]" data-amt data-fund="{{ $f->id }}" class="form-control" step="0.01" min="0" value="0" style="width:170px;margin-left:auto;text-align:right;"><input type="hidden" name="rows[{{ $i }}][fund_id]" value="{{ $f->id }}"></td>
        </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td style="text-align:right;"><strong>Split Total</strong></td><td></td><td style="text-align:right;"><strong id="ms-total">₱0.00</strong></td></tr>
        <tr><td style="text-align:right;color:#64748b;">Budget remaining after (counter)</td><td></td><td style="text-align:right;color:#64748b;" id="ms-budget-after">—</td></tr></tfoot></table>
        </div>
        <div style="margin-top:10px;"><button class="btn btn-primary" type="submit">Generate Split Allocations</button></div>
    </form>
</div>
<script>
(function(){
  const previewUrl = "{{ route('admin.funds.budget-preview') }}";
  const idInput = document.getElementById('ms_budget_id');
  const loadBtn = document.getElementById('ms_budget_load');
  const msg = document.getElementById('ms-budget-msg');
  const hidden = document.getElementById('ms_budget_hidden');
  const box = document.getElementById('ms-budget-preview');
  const label = document.getElementById('msbp-label');
  const lines = document.getElementById('msbp-lines');
  const budAfter = document.getElementById('ms-budget-after');
  const totalEl = document.getElementById('ms-total');
  const target = document.getElementById('ms_target');
  const dateI = document.getElementById('ms_date');
  let budRem = null, budId = null;
  const peso = n => '₱' + (parseFloat(n)||0).toLocaleString('en-PH',{minimumFractionDigits:2});
  const amts = () => Array.from(document.querySelectorAll('#ms-rows input[data-amt]'));

  function renderBudget(d){
    box.style.display='block';
    label.textContent = 'Budget #' + d.id + ' — ' + d.label.replace(/^#\d+ — /,'');
    lines.innerHTML = 'Allocated: <strong>'+peso(d.allocated)+'</strong> | Used: <strong>'+peso(d.utilized)+'</strong> | Remaining: <strong>'+peso(d.remaining)+'</strong><br><span style="color:#64748b;">'+(d.department||'')+' · '+(d.fiscal_year||'')+'</span>';
    budRem = parseFloat(d.remaining)||0; budId = d.id; hidden.value = d.id;
    msg.textContent = 'Nahanap: Budget #' + d.id + '.';
    recalc();
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

  function recalc(){
    let t=0;
    amts().forEach(i=>{ t += parseFloat(i.value)||0; });
    t = Math.round(t*100)/100;
    totalEl.textContent = peso(t);
    if(budRem===null){ budAfter.textContent='—'; budAfter.style.color=''; }
    else { const ba=Math.round((budRem-t)*100)/100; budAfter.textContent=peso(ba)+(ba<0?' (sobra!)':''); budAfter.style.color = ba<0 ? '#b91c1c' : ''; }
  }
  amts().forEach(i=>i.addEventListener('input',recalc));

  // Auto-split: hatiin ang budget remaining sa mga fund (water-filling — bawat fund hanggang kaya lang).
  document.getElementById('ms_auto').addEventListener('click',()=>{
    if(budRem===null || !budId){ alert('Mag-load muna ng Budget ID.'); return; }
    let need = Math.round(budRem*100)/100;
    const rows = amts();
    rows.forEach(i=>{ i.value = 0; });
    let pool = rows.map(i=>({el:i, avail: parseFloat(i.closest('tr').dataset.avail)||0}));
    pool = pool.filter(p=>p.avail>0);
    let guard = 1000;
    while(need > 0.009 && pool.length && guard-- > 0){
      const share = Math.floor((need/pool.length)*100)/100;
      if(share <= 0){ pool[0].el.value = (parseFloat(pool[0].el.value)||0) + Math.round(need*100)/100; break; }
      const next = [];
      pool.forEach(p=>{
        const cur = parseFloat(p.el.value)||0;
        const give = Math.min(p.avail-cur, share, need);
        if(give>0){ p.el.value = (Math.round((cur+give)*100)/100).toFixed(2); need = Math.round((need-give)*100)/100; }
        if((p.avail-(parseFloat(p.el.value)||0)) > 0.009 && need > 0.009) next.push(p);
      });
      pool = next;
    }
    recalc();
    msg.textContent = need > 0.009 ? 'Kulang ang funds — mano-manong ayusin ang amounts.' : 'Auto-split tapos na. Puwede pang i-edit bawat amount.';
  });

  document.getElementById('ms-form').addEventListener('submit',e=>{
    hidden.value = budId || '';
    document.getElementById('ms_target_hidden').value = target.value.trim();
    document.getElementById('ms_date_hidden').value = dateI.value;
    if(!budId){ e.preventDefault(); alert('Mag-load muna ng Budget ID.'); return; }
    if(!target.value.trim()){ e.preventDefault(); alert('Ilagay ang Allocated To (department/program).'); return; }
    let t=0;
    amts().forEach(i=>{ t += parseFloat(i.value)||0; });
    t = Math.round(t*100)/100;
    if(t<=0){ e.preventDefault(); alert('Maglagay ng amount sa kahit isang fund.'); return; }
    if(budRem!==null && t>budRem){ e.preventDefault(); alert('Total '+peso(t)+' ay lumampas sa budget remaining '+peso(budRem)+'.'); return; }
  });
  recalc();
})();
</script>

<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Fund Name</th><th>Type</th><th>Source</th><th style="text-align:right;">Initial</th><th style="text-align:right;">Current</th><th style="text-align:right;">Reserved</th><th style="text-align:right;">Used</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($funds as $f)
            <tr>
                <td><a href="{{ route('admin.funds.show', $f) }}">{{ $f->fund_name }}</a></td>
                <td>{{ ucfirst($f->fund_type) }}</td><td>{{ $f->fund_source ?? '—' }}</td>
                <td style="text-align:right;">P{{ number_format($f->initial_balance, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($f->current_balance, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($f->reserved_amount, 2) }}</td>
                <td style="text-align:right;">P{{ number_format($f->used_amount, 2) }}</td>
                <td><span class="status {{ $f->status === 'active' ? 'st-green' : 'st-gray' }}">{{ ucfirst($f->status) }}</span></td>
                <td><div class="fms-actions"><a class="btn btn-sm btn-secondary" href="{{ route('admin.funds.show', $f) }}">View</a><a class="btn btn-sm btn-secondary" href="{{ route('admin.funds.edit', $f) }}">Edit</a></div></td>
            </tr>
        @empty
            <tr><td colspan="9" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $funds->links() }}</div>
</div>
@endsection
