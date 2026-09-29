@extends('layouts.admin')
@section('title', 'Fund Details')
@section('content')
<div class="page-header">
    <h2>{{ $fund->fund_name }}</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.funds.edit', $fund) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.funds.index') }}">Back to List</a>
    </div>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Initial Balance</h4><p class="val">P{{ number_format($fund->initial_balance, 2) }}</p></div>
    <div class="fms-stat"><h4>Current Balance</h4><p class="val">P{{ number_format($fund->current_balance, 2) }}</p></div>
    <div class="fms-stat"><h4>Reserved</h4><p class="val">P{{ number_format($fund->reserved_amount, 2) }}</p></div>
    <div class="fms-stat"><h4>Available</h4><p class="val">P{{ number_format($fund->available_amount, 2) }}</p></div>
</div>

<div class="fms-panel">
    <h3>Post Fund Transaction</h3>
    <form method="POST" action="{{ route('admin.funds.transactions', $fund) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        @csrf
        <div><label style="font-size:0.8rem;">Type *</label><br><select name="transaction_type" class="form-control" required><option value="inflow">Inflow</option><option value="outflow">Outflow</option><option value="reservation">Reservation</option><option value="release">Release</option></select></div>
        <div><label style="font-size:0.8rem;">Amount (PHP) *</label><br><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
        <div><label style="font-size:0.8rem;">Date *</label><br><input type="date" name="transaction_date" class="form-control" value="{{ today()->toDateString() }}" required></div>
        <div><label style="font-size:0.8rem;">Reference</label><br><input type="text" name="reference_number" class="form-control"></div>
        <div><button class="btn btn-primary" type="submit">Post</button></div>
    </form>
</div>

<div class="fms-panel">
    <h3>Split Budget to Departments (auto-generate allocations)</h3>
    @if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px;">
        <div><label style="font-size:0.8rem;">Budget ID * <span style="color:#64748b;">— i-type ang ID, kusang lalabas</span></label><br>
        <div style="display:flex;gap:8px;"><input type="text" id="split_budget_id" class="form-control" placeholder="hal. 25" autocomplete="off" style="width:130px;"><button type="button" id="split_budget_load" class="btn btn-secondary">Load</button></div>
        <small id="split-budget-msg" style="color:#64748b;"></small></div>
        <div style="min-width:280px;flex:1;"><label style="font-size:0.8rem;">Approved Budget</label><br>
        <select id="split_budget_select" name="budget_plan_id" class="form-control" style="width:100%;"><option value="">— Pumili ng budget —</option>@foreach($budgets ?? [] as $b)<option value="{{ $b->id }}" data-alloc="{{ (float)$b->allocated_amount }}" data-used="{{ (float)$b->utilized_amount }}" data-rem="{{ (float)$b->remaining_amount }}">#{{ $b->id }} — {{ $b->budget_name }} (Rem ₱{{ number_format($b->remaining_amount,2) }})</option>@endforeach</select></div>
    </div>
    <div id="split-budget-preview" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:12px;display:none;">
        <strong id="sbp-label">—</strong><br><span id="sbp-lines" style="font-size:0.85rem;"></span>
    </div>
    <form method="POST" action="{{ route('admin.funds.allocate', $fund) }}" id="split-form">
        @csrf
        <input type="hidden" name="budget_plan_id" id="split_budget_hidden" value="">
        <table class="fms-table"><thead><tr><th>Allocated To (Dept / Program) *</th><th style="text-align:right;">Amount (PHP) *</th><th></th></tr></thead>
        <tbody id="split-rows"></tbody>
        <tfoot><tr><td style="text-align:right;"><strong>Split Total</strong></td><td style="text-align:right;"><strong id="split-total">₱0.00</strong></td><td></td></tr>
        <tr><td style="text-align:right;color:#64748b;">Fund remaining after</td><td style="text-align:right;color:#64748b;" id="split-fund-after">₱{{ number_format($fund->available_amount,2) }}</td><td></td></tr>
        <tr><td style="text-align:right;color:#64748b;">Budget remaining after</td><td style="text-align:right;color:#64748b;" id="split-budget-after">—</td><td></td></tr></tfoot></table>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;">
            <button type="button" id="split-add" class="btn btn-secondary">+ Add Row</button>
            <div><label style="font-size:0.8rem;">Date *</label><br><input type="date" name="allocation_date" class="form-control" value="{{ today()->toDateString() }}" required></div>
            <div><button class="btn btn-primary" type="submit">Generate Allocations</button></div>
        </div>
    </form>
</div>
<script>
(function(){
  const fundAvail = {{ (float)$fund->available_amount }};
  const previewUrl = "{{ route('admin.funds.budget-preview') }}";
  const idInput = document.getElementById('split_budget_id');
  const loadBtn = document.getElementById('split_budget_load');
  const msg = document.getElementById('split-budget-msg');
  const sel = document.getElementById('split_budget_select');
  const hidden = document.getElementById('split_budget_hidden');
  const box = document.getElementById('split-budget-preview');
  const label = document.getElementById('sbp-label');
  const lines = document.getElementById('sbp-lines');
  const rows = document.getElementById('split-rows');
  const totalEl = document.getElementById('split-total');
  const fundAfter = document.getElementById('split-fund-after');
  const budAfter = document.getElementById('split-budget-after');
  let budRem = null;
  const peso = n => '₱' + (parseFloat(n)||0).toLocaleString('en-PH',{minimumFractionDigits:2});

  function renderBudget(d){
    box.style.display='block';
    label.textContent = 'Budget #' + d.id + ' — ' + d.label.replace(/^#\d+ — /,'');
    lines.innerHTML = 'Allocated: <strong>'+peso(d.allocated)+'</strong> | Used: <strong>'+peso(d.utilized)+'</strong> | Remaining: <strong>'+peso(d.remaining)+'</strong><br><span style="color:#64748b;">'+(d.department||'')+' · '+(d.fiscal_year||'')+' · '+d.status+'</span>';
    budRem = parseFloat(d.remaining)||0;
    let matched = false;
    for(const o of sel.options){ if(o.value==String(d.id)){ sel.value=String(d.id); matched=true; break; } }
    hidden.value = d.id;
    msg.textContent = 'Nahanap: Budget #' + d.id + '.';
    recalc();
  }
  function syncFromSelect(){
    const o = sel.options[sel.selectedIndex];
    hidden.value = sel.value;
    idInput.value = sel.value;
    if(!o || !o.value){ box.style.display='none'; budRem=null; msg.textContent=''; recalc(); return; }
    renderBudget({id:o.value,label:o.text,department:'',fiscal_year:'',status:'',allocated:o.dataset.alloc,utilized:o.dataset.used,remaining:o.dataset.rem});
  }
  async function loadById(auto){
    const q = idInput.value.trim();
    if(!q){ if(!auto) msg.textContent='Maglagay muna ng Budget ID.'; return; }
    if(!auto) msg.textContent='Hinahanap…';
    try{
      const res = await fetch(previewUrl + '?q=' + encodeURIComponent(q), {headers:{'Accept':'application/json'}});
      if(res.status===404){ if(!auto) msg.textContent='Walang approved budget para sa "'+q+'".'; else { box.style.display='none'; } return; }
      if(!res.ok) throw 0;
      renderBudget(await res.json());
    }catch(e){ if(!auto) msg.textContent='Hindi ma-load. Subukang muli.'; }
  }
  loadBtn.addEventListener('click', ()=>loadById(false));
  idInput.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); loadById(false); } });
  let deb=null;
  idInput.addEventListener('input', ()=>{ clearTimeout(deb); const q=idInput.value.trim(); if(!q){msg.textContent='';return;} deb=setTimeout(()=>loadById(true),600); });
  sel.addEventListener('change', syncFromSelect);

  function recalc(){
    let t=0;
    rows.querySelectorAll('input[data-amt]').forEach(i=>{ t += parseFloat(i.value)||0; });
    t = Math.round(t*100)/100;
    totalEl.textContent = peso(t);
    const fa = Math.round((fundAvail-t)*100)/100;
    fundAfter.textContent = peso(fa);
    fundAfter.style.color = fa<0 ? '#b91c1c' : '';
    if(budRem===null){ budAfter.textContent='—'; budAfter.style.color=''; }
    else { const ba=Math.round((budRem-t)*100)/100; budAfter.textContent=peso(ba); budAfter.style.color = ba<0 ? '#b91c1c' : ''; }
  }
  function addRow(to='',amt=''){
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input type="text" name="splits[IDX][allocated_to]" class="form-control" placeholder="Department / Program" value="" required style="width:100%;"></td>'
      +'<td style="text-align:right;"><input type="number" name="splits[IDX][amount]" data-amt class="form-control" step="0.01" min="0.01" value="" required style="width:170px;margin-left:auto;"></td>'
      +'<td><button type="button" class="btn btn-sm btn-danger">×</button></td>';
    tr.querySelector('input[type=text]').value=to;
    const amtI=tr.querySelector('input[data-amt]'); amtI.value=amt;
    amtI.addEventListener('input',recalc);
    tr.querySelector('button').addEventListener('click',()=>{ if(rows.rows.length>1){tr.remove();recalc();} });
    rows.appendChild(tr);
    renumber();
  }
  function renumber(){
    Array.from(rows.rows).forEach((tr,i)=>{
      tr.querySelector('input[type=text]').name='splits['+i+'][allocated_to]';
      tr.querySelector('input[data-amt]').name='splits['+i+'][amount]';
    });
    recalc();
  }
  document.getElementById('split-add').addEventListener('click',()=>addRow());
  document.getElementById('split-form').addEventListener('submit',e=>{
    renumber();
    let t=0,n=0;
    rows.querySelectorAll('input[data-amt]').forEach(i=>{ t+=parseFloat(i.value)||0; });
    rows.querySelectorAll('input[type=text]').forEach(i=>{ if(i.value.trim()) n++; });
    if(!n){ e.preventDefault(); alert('Magdagdag ng hindi bababa sa isang row.'); return; }
    if(t>fundAvail){ e.preventDefault(); alert('Split total '+peso(t)+' ay lumampas sa available fund '+peso(fundAvail)+'.'); return; }
    if(budRem!==null && t>budRem){ e.preventDefault(); alert('Split total '+peso(t)+' ay lumampas sa budget remaining '+peso(budRem)+'.'); return; }
  });
  addRow();
})();
</script>

<div class="fms-two">
    <div class="fms-panel">
        <h3>Transactions</h3>
        <table class="fms-table"><thead><tr><th>Date</th><th>Type</th><th style="text-align:right;">Amount</th><th>Reference</th></tr></thead><tbody>
        @forelse($fund->transactions as $t)<tr><td>{{ $t->transaction_date }}</td><td>{{ ucfirst($t->transaction_type) }}</td><td style="text-align:right;">P{{ number_format($t->amount, 2) }}</td><td>{{ $t->reference_number ?? '—' }}</td></tr>
        @empty<tr><td colspan="4" style="color:#64748b;">No financial records available.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="fms-panel">
        <h3>Allocations</h3>
        <table class="fms-table"><thead><tr><th>Date</th><th>Budget ID</th><th>Allocated To</th><th style="text-align:right;">Amount</th><th>Status</th></tr></thead><tbody>
        @forelse($fund->allocations as $a)<tr><td>{{ $a->allocation_date }}</td><td>@if($a->budget_plan_id)<strong>#{{ $a->budget_plan_id }}</strong>@else<span style="color:#64748b;">—</span>@endif</td><td>{{ $a->allocated_to ?? ($a->budgetPlan->budget_name ?? '—') }}</td><td style="text-align:right;">P{{ number_format($a->amount, 2) }}</td><td><span class="status">{{ \App\Services\WorkflowService::label($a->status) }}</span></td></tr>
        @empty<tr><td colspan="5" style="color:#64748b;">No financial records available.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>

@if(!empty($pendingAllocations) && $pendingAllocations->count())
<div class="fms-panel">
    <h3>Pending Accountant Submissions — Review in this module (same records)</h3>
    <table class="fms-table"><thead><tr><th>ID</th><th>Target</th><th>Budget</th><th style="text-align:right;">Amount</th><th>Submitted</th><th>Actions</th></tr></thead><tbody>
    @foreach($pendingAllocations as $p)
    <tr><td>#{{ $p->id }}</td><td>{{ $p->allocated_to }}<br><span style="color:#64748b;">{{ $p->purpose }}</span></td><td>@if($p->budget_plan_id)<strong>#{{ $p->budget_plan_id }}</strong> — {{ $p->budgetPlan->budget_name ?? '' }}@else<span style="color:#64748b;">—</span>@endif</td><td style="text-align:right;">P{{ number_format($p->amount, 2) }}</td><td>{{ $p->submitted_at?->format('M d, Y') ?? '—' }}</td>
    <td><div class="fms-actions">
        <form method="POST" action="{{ route('admin.funds.allocations.approve', $p) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Approve</button></form>
        <form method="POST" action="{{ route('admin.funds.allocations.reject', $p) }}">@csrf<input type="text" name="rejection_reason" placeholder="Reason (required)" class="form-control" required style="max-width:200px;"><button class="btn btn-sm btn-danger" type="submit">Reject</button></form>
    </div></td></tr>
    @endforeach
    </tbody></table>
</div>
@endif
@endsection
