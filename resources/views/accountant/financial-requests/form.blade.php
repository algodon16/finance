@extends('layouts.app')
@section('title', 'Prepare Financial Request')
@section('content')
<style>
.fr-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
.fr-info-grid .form-group{margin-bottom:0;}
.fr-info-grid .span-2{grid-column:1/-1;}
.fr-link-row{display:grid;grid-template-columns:180px 140px;gap:12px;justify-content:start;}
.fr-budget-panel{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-top:12px;}
.fr-budget-panel .row{display:flex;justify-content:space-between;gap:8px;padding:4px 0;flex-wrap:wrap;}
.fr-budget-panel .row strong{font-size:1.05rem;}
.fr-budget-ok{color:#15803d;}
.fr-budget-over{color:#b91c1c;}
.fr-dept-list{margin:6px 0 0;padding:0;list-style:none;max-height:150px;overflow:auto;}
.fr-dept-list li{display:flex;justify-content:space-between;gap:8px;padding:4px 6px;border-radius:6px;cursor:pointer;}
.fr-dept-list li:hover{background:#eff6ff;}
input[list]::-webkit-calendar-picker-indicator{display:none !important;}
@media(max-width:900px){.fr-info-grid{grid-template-columns:1fr;}.fr-link-row{grid-template-columns:1fr;}}
</style>
<div class="page-header"><div><h2>{{ isset($record->id) ? 'Revise Request' : 'Prepare Internal Request' }}</h2><p class="page-subtitle">Internal requests are tagged as Financial Management origin. External subsystem requests arrive via integration and are never re-created here.</p></div><a href="{{ route('accountant.financial-requests.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="POST" action="{{ isset($record->id) ? route('accountant.financial-requests.update',$record) : route('accountant.financial-requests.store') }}" enctype="multipart/form-data" id="finReqForm">
@csrf @if(isset($record->id)) @method('PUT') @endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Information</h3>
<div class="fr-info-grid">
<div class="form-group"><label>Request Type</label><input type="text" name="request_type" class="form-control" list="reqTypeList" value="{{ old('request_type',$record->request_type) }}" placeholder="e.g. budget" required><datalist id="reqTypeList">@foreach(\App\Models\FinancialRequest::TYPES as $t)<option value="{{ $t }}"></option>@endforeach</datalist></div>
<div class="form-group"><label>Department</label><input type="text" name="department" id="deptInput" class="form-control" list="deptList" value="{{ old('department',$record->department) }}" placeholder="e.g. College of Computer Studies" required><datalist id="deptList">@foreach(($departments ?? []) as $d)<option value="{{ $d->name }}"></option>@endforeach</datalist></div>
<div class="form-group"><label>Date</label><input type="date" name="request_date" class="form-control" value="{{ old('request_date', isset($record->request_date) && $record->request_date ? \Carbon\Carbon::parse($record->request_date)->format('Y-m-d') : date('Y-m-d')) }}" required></div>
@php
$fallbackBudgetId = isset($record->reference_type) && $record->reference_type === \App\Models\BudgetPlan::class ? $record->reference_id : null;
$selectedBudgetId = old('budget_plan_id', $record->budget_plan_id ?? $fallbackBudgetId);
@endphp
<div class="form-group span-2"><label>Budget ID (Budget Plan)</label>
<input type="text" name="budget_plan_id" id="budgetPlanId" class="form-control" inputmode="numeric" pattern="[0-9]*" list="budgetIdList" placeholder="e.g. 3 — type Budget ID" value="{{ $selectedBudgetId }}">
<datalist id="budgetIdList">
@foreach(($budgets ?? []) as $b)<option value="{{ $b->id }}">#{{ $b->id }} — {{ $b->budget_name }} ({{ $b->academic_year }}) — Rem ₱{{ number_format((float) $b->remaining_amount,2) }}</option>@endforeach
</datalist>
<p class="summary-desc" id="linkHint">I-type ang Budget ID — automatic na magpapakita ang Available / Remaining sa kanan.</p>
<div id="deptBudgets" style="display:none;"><p class="summary-desc" style="margin:6px 0 0;"><strong>Budgets ng department na ito</strong> (click para gamitin ang ID):</p><ul class="fr-dept-list" id="deptBudgetList"></ul></div>
</div>
<div class="form-group span-2"><label>Description</label><input type="text" name="description" class="form-control" value="{{ old('description',$record->description) }}" placeholder="e.g. Purchase of office supplies" maxlength="5000" required></div>
</div>
</div>
<div>
<div class="dashboard-card"><h3>Budget Availability</h3>
<p class="summary-desc" id="linkLabel">Walang Budget ID na nilagay.</p>
<div class="fr-budget-panel">
<div class="row"><span>Available Budget:</span><strong id="availBudget">₱0.00</strong></div>
<div class="row"><span>Budget Items Total:</span><strong id="itemsTotal">₱0.00</strong></div>
<div class="row"><span>Remaining Budget (matitira):</span><strong id="remainBudget" class="fr-budget-ok">₱0.00</strong></div>
</div>
<p class="summary-desc" id="budgetWarn" style="display:none;color:#b91c1c;font-weight:600;">Lampas sa available budget ang items total.</p>
<p class="summary-desc" style="margin-top:8px;">Total Amount (auto from Budget Items): <strong id="amountPreview">₱{{ number_format((float) old('amount',$record->amount ?? 0),2) }}</strong></p>
</div>
</div>
</div>

{{-- ============ BUDGET ITEMS ============ --}}
<div class="dashboard-card" style="margin-top:16px;">
<div class="card-head-row"><h3>Budget Items</h3><button type="button" class="btn btn-secondary" id="addItem">+ Add Item</button></div>
<div class="table-responsive"><table class="table" id="itemsTable"><thead><tr><th>Item Name</th><th>Category</th><th>Supplier</th><th style="width:90px;">Quantity</th><th style="width:150px;">Unit Cost</th><th style="width:140px;">Total</th><th style="width:50px;"></th></tr></thead><tbody>
@php
$cats = ['Equipment','Supplies','Maintenance','Utilities','Training','Software/Technology','Procurement','Other Expenses'];
$metaItems = is_array($record->metadata ?? null) ? ($record->metadata['items'] ?? []) : [];
$rows = old('items', !empty($metaItems) ? $metaItems : [['item_name'=>'','category'=>'','supplier'=>'','quantity'=>'','unit_cost'=>'']]);
@endphp
@foreach($rows as $i=>$r)<tr class="item-row">
<td><input type="text" name="items[{{ $i }}][item_name]" class="form-control iname" list="itemList" value="{{ $r['item_name'] ?? '' }}" placeholder="Item name" required></td>
<td><select name="items[{{ $i }}][category]" class="form-control cat"><option value="">—</option>@foreach($cats as $c)<option value="{{ $c }}" {{ ($r['category'] ?? '')===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select></td>
<td><input type="text" name="items[{{ $i }}][supplier]" class="form-control sup" list="supplierList" value="{{ $r['supplier'] ?? '' }}" placeholder="Supplier"></td>
<td><input type="number" name="items[{{ $i }}][quantity]" class="form-control qty" min="1" max="1000000" value="{{ $r['quantity'] ?? '' }}" placeholder="0" required></td>
<td><input type="number" step="0.01" name="items[{{ $i }}][unit_cost]" class="form-control cost" min="0.01" value="{{ $r['unit_cost'] ?? '' }}" placeholder="0.00" required></td>
<td class="line-total" style="font-weight:700;white-space:nowrap;">₱0.00</td>
<td><button type="button" class="btn btn-sm btn-secondary remove-item" title="Remove">×</button></td>
</tr>@endforeach
</tbody></table></div>
<datalist id="supplierList">@foreach(($suppliers ?? []) as $s)<option value="{{ $s->name }}"></option>@endforeach</datalist>
<datalist id="itemList">@foreach(($catalogItems ?? []) as $ci)<option value="{{ $ci->item_name }}">{{ $ci->supplier->name ?? '' }}{{ $ci->category ? ' · '.$ci->category : '' }}</option>@endforeach</datalist>
</div>

<div style="margin-top:12px;display:flex;gap:8px;"><button name="action" value="draft" class="btn btn-secondary">Save Draft</button>@if(!isset($record->id))<button name="action" value="submit" class="btn btn-primary">Save &amp; Submit for Admin Approval</button>@else<button class="btn btn-primary">Save Revision as Draft</button>@endif</div>
</form>

@push('scripts')<script>
const linkPreviewUrl = "{{ route('accountant.financial-requests.link-preview') }}";
const deptBudgetsUrl = "{{ route('accountant.financial-requests.department-budgets') }}";
const itemLookupUrl = "{{ route('accountant.financial-requests.item-lookup') }}";
const supplierItemsUrl = "{{ route('accountant.financial-requests.supplier-items') }}";
let linkedAvailable = null;
let fullItemListHtml = '';
function snapshotItemList(){ const dl=document.getElementById('itemList'); if(dl&&!fullItemListHtml) fullItemListHtml=dl.innerHTML; }
function peso(n){return '₱'+(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}
function itemsGrandTotal(){
  let grand=0;
  document.querySelectorAll('.item-row').forEach(r=>{
    const q=parseFloat(r.querySelector('.qty').value)||0, c=parseFloat(r.querySelector('.cost').value)||0;
    const line=q*c; grand+=line;
    r.querySelector('.line-total').textContent=peso(line);
  });
  return grand;
}
function renderBudgetPanel(grand){
  document.getElementById('itemsTotal').textContent=peso(grand);
  document.getElementById('amountPreview').textContent=peso(grand);
  if(linkedAvailable===null){
    document.getElementById('availBudget').textContent='—';
    document.getElementById('remainBudget').textContent='—';
    document.getElementById('remainBudget').className='';
    document.getElementById('budgetWarn').style.display='none';
    return;
  }
  const remain=linkedAvailable-grand;
  document.getElementById('availBudget').textContent=peso(linkedAvailable);
  const rb=document.getElementById('remainBudget');
  rb.textContent=peso(remain);
  rb.className=remain<0?'fr-budget-over':'fr-budget-ok';
  document.getElementById('budgetWarn').style.display=remain<0?'block':'none';
}
function recalc(){ renderBudgetPanel(itemsGrandTotal()); }
async function fetchBudgetById(){
  const sel=document.getElementById('budgetPlanId');
  const label=document.getElementById('linkLabel'), hint=document.getElementById('linkHint');
  if(!sel||!String(sel.value||'').trim()){
    linkedAvailable=null; label.textContent='Walang Budget ID na nilagay.';
    hint.textContent='I-type ang Budget ID — automatic na magpapakita ang Available / Remaining sa kanan.';
    recalc();
    return false;
  }
  const id=parseInt(String(sel.value).trim())||0;
  if(!id){ linkedAvailable=null; label.textContent='Invalid Budget ID.'; recalc(); return true; }
  label.textContent='Hinahanap ang Budget #'+id+'…';
  try{
    const res=await fetch(linkPreviewUrl+'?kind=budget&id='+id,{headers:{'Accept':'application/json'}});
    const d=await res.json();
    if(!d.found){ linkedAvailable=null; label.textContent='Budget ID #'+id+' hindi nakita.'; hint.textContent='Walang budget na may ganyang ID.'; recalc(); return true; }
    linkedAvailable=(d.available===null||d.available===undefined)?null:parseFloat(d.available);
    label.textContent='Budget ID #'+d.id+' · '+d.label+(d.department?' · '+d.department:'')+(d.status?' · '+d.status:'');
    hint.textContent='Budget ID #'+d.id+' · '+d.label;
  }catch(e){ linkedAvailable=null; label.textContent='Hindi ma-load ang Budget.'; }
  recalc();
  return true;
}
async function fetchPreview(){
  await fetchBudgetById();
}
// Item → Supplier: pag naglagay ng item name, hanapin ang supplier/category/presyo sa catalog.
async function lookupItemForRow(nameInput){
  const name=String(nameInput.value||'').trim();
  if(!name) return;
  const tr=nameInput.closest('tr'); if(!tr) return;
  try{
    const res=await fetch(itemLookupUrl+'?name='+encodeURIComponent(name),{headers:{'Accept':'application/json'}});
    const d=await res.json();
    if(!d.found) return;
    const sup=tr.querySelector('.sup'), cat=tr.querySelector('.cat'), cost=tr.querySelector('.cost');
    if(sup&&!String(sup.value||'').trim()&&d.supplier) sup.value=d.supplier;
    if(cat&&!cat.value&&d.category){
      const opt=[...cat.options].find(o=>o.value===d.category);
      if(opt) cat.value=d.category;
    }
    if(cost&&!String(cost.value||'').trim()&&d.unit_cost!==null&&d.unit_cost!==undefined) cost.value=d.unit_cost;
    recalc();
  }catch(e){}
}
// Supplier → Items: pag naglagay ng supplier, ipakita lang ang items niya sa suggestions.
async function filterItemsBySupplier(supInput){
  const dl=document.getElementById('itemList'); if(!dl) return;
  snapshotItemList();
  const name=String(supInput.value||'').trim();
  if(!name){ dl.innerHTML=fullItemListHtml; return; }
  try{
    const res=await fetch(supplierItemsUrl+'?supplier='+encodeURIComponent(name),{headers:{'Accept':'application/json'}});
    const d=await res.json();
    if(!d.items||!d.items.length) return;
    dl.innerHTML='';
    d.items.forEach(it=>{
      const o=document.createElement('option');
      o.value=it.item_name;
      o.textContent=(it.category?it.category+' · ':'')+(it.unit_cost!==null&&it.unit_cost!==undefined?'₱'+it.unit_cost:'');
      dl.appendChild(o);
    });
  }catch(e){}
}
let deptTimer=null;
async function fetchDeptBudgets(){
  const dept=document.getElementById('deptInput').value.trim();
  const box=document.getElementById('deptBudgets'), list=document.getElementById('deptBudgetList');
  if(!dept){ box.style.display='none'; return; }
  try{
    const res=await fetch(deptBudgetsUrl+'?department='+encodeURIComponent(dept),{headers:{'Accept':'application/json'}});
    const d=await res.json();
    if(!d.budgets||!d.budgets.length){ box.style.display='none'; return; }
    list.innerHTML='';
    d.budgets.forEach(b=>{
      const li=document.createElement('li');
      li.innerHTML='<span><strong>#'+b.id+'</strong> '+b.name+' ('+b.academic_year+')</span><span>'+peso(b.remaining)+'</span>';
      li.title='Click para gamitin ang ID '+b.id;
      li.addEventListener('click',()=>{
        const sel=document.getElementById('budgetPlanId');
        if(sel){
          sel.value=String(b.id);
        }
        fetchPreview();
      });
      list.appendChild(li);
    });
    const total=document.createElement('li');
    total.style.cursor='default'; total.style.fontWeight='700';
    total.innerHTML='<span>Total natitira ng department</span><span>'+peso(d.total_remaining)+'</span>';
    list.appendChild(total);
    box.style.display='block';
  }catch(e){}
}
document.addEventListener('input',e=>{
  if(e.target.classList&&(e.target.classList.contains('qty')||e.target.classList.contains('cost'))) recalc();
  if(e.target.id==='budgetPlanId'){ clearTimeout(e.target._t); e.target._t=setTimeout(fetchPreview,400); }
  if(e.target.id==='deptInput'){ clearTimeout(deptTimer); deptTimer=setTimeout(fetchDeptBudgets,500); }
  if(e.target.classList&&e.target.classList.contains('iname')){ clearTimeout(e.target._t); e.target._t=setTimeout(()=>lookupItemForRow(e.target),600); }
  if(e.target.classList&&e.target.classList.contains('sup')){ clearTimeout(e.target._t); e.target._t=setTimeout(()=>filterItemsBySupplier(e.target),600); }
});
document.addEventListener('change',e=>{
  if(e.target.id==='budgetPlanId') fetchPreview();
  if(e.target.id==='deptInput') fetchDeptBudgets();
  if(e.target.classList&&e.target.classList.contains('iname')) lookupItemForRow(e.target);
  if(e.target.classList&&e.target.classList.contains('sup')) filterItemsBySupplier(e.target);
  if(e.target.classList&&(e.target.classList.contains('qty')||e.target.classList.contains('cost')||e.target.classList.contains('cat'))) recalc();
});
document.addEventListener('click',e=>{
  if(e.target.id==='addItem'){
    const tb=document.querySelector('#itemsTable tbody'), i=tb.rows.length, r=tb.rows[0].cloneNode(true);
    r.querySelectorAll('input').forEach(inp=>{ inp.value=''; inp.name=inp.name.replace(/\[\d+\]/,'['+i+']'); });
    r.querySelectorAll('select').forEach(s=>{ s.selectedIndex=0; s.name=s.name.replace(/\[\d+\]/,'['+i+']'); });
    tb.appendChild(r); recalc();
  }
  if(e.target.classList.contains('remove-item')){ if(document.querySelectorAll('.item-row').length>1) e.target.closest('tr').remove(); recalc(); }
});
recalc(); fetchPreview(); fetchDeptBudgets(); snapshotItemList();
</script>@endpush
@endsection
