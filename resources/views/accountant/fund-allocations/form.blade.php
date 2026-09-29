@extends('layouts.app')
@section('title', 'Prepare Fund Allocation')
@section('content')
<div class="page-header"><div><h2>{{ isset($record->id) ? 'Revise Allocation (Rev '.$record->revision_number.')' : 'Prepare Allocation' }}</h2><p class="page-subtitle">Shows Approved Budget / Already Allocated / Remaining / Proposed. Submission locks editing.</p></div><a href="{{ route('accountant.fund-allocations.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@if(($funds ?? collect())->isEmpty())<div class="alert alert-error"><strong>Walang active fund.</strong> Hindi makakapag-prepare ng allocation hangga't walang fund — makipag-ugnayan sa admin para gumawa muna ng Fund sa Fund Management and Allocation.</div>@endif
<form method="POST" action="{{ isset($record->id) ? route('accountant.fund-allocations.update',$record) : route('accountant.fund-allocations.store') }}" enctype="multipart/form-data">
@csrf @if(isset($record->id)) @method('PUT') @endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Allocation Form</h3>
<div class="form-group"><label>Fund (source)</label><select name="fund_id" class="form-control" id="fundSelect" required>@foreach($funds as $f)<option value="{{ $f->id }}" data-avail="{{ (float)$f->available_amount }}" {{ old('fund_id',$record->fund_id)==$f->id?'selected':'' }}>#{{ $f->id }} — {{ $f->fund_name }} (Avail ₱{{ number_format($f->available_amount,2) }})</option>@endforeach</select></div>
<div class="form-group"><label>Budget ID <span class="summary-desc">— i-type ang ID (hal. 25), kusang lalabas ang budget</span></label>
<div style="display:flex;gap:8px;">
<input type="text" id="budget_lookup" class="form-control" placeholder="Budget ID…" autocomplete="off" value="{{ old('budget_plan_id',$record->budget_plan_id) }}">
<button type="button" id="budget_load" class="btn btn-secondary" style="white-space:nowrap;">Load</button>
</div>
<small class="summary-desc" id="budget-msg"></small></div>
<div class="form-group"><label>Approved Budget (target)</label><select name="budget_plan_id" id="budgetSelect" class="form-control"><option value="">— None —</option>@foreach($budgets as $b)<option value="{{ $b->id }}" data-alloc="{{ (float)$b->allocated_amount }}" data-used="{{ (float)$b->utilized_amount }}" data-rem="{{ (float)$b->remaining_amount }}" {{ old('budget_plan_id',$record->budget_plan_id)==$b->id?'selected':'' }}>#{{ $b->id }} — {{ $b->budget_name }} (Alloc ₱{{ number_format($b->allocated_amount,2) }} | Used ₱{{ number_format($b->utilized_amount,2) }} | Rem ₱{{ number_format($b->remaining_amount,2) }})</option>@endforeach</select></div>
<div id="budget-preview" class="dashboard-card" style="background:#f8fafc;margin-top:4px;display:none;">
<p style="margin:0 0 4px;"><strong id="bp-label">—</strong></p>
<p style="margin:0;" id="bp-lines"></p>
</div>
<div class="form-group"><label>Target Department / Category</label><input type="text" name="allocated_to" class="form-control" value="{{ old('allocated_to',$record->allocated_to) }}" required></div>
<div class="form-group"><label>Allocation Amount</label><input type="number" step="0.01" min="0.01" name="amount" id="allocAmount" class="form-control" value="{{ old('amount',$record->amount) }}" required></div>
<div class="form-group"><label>Allocation Date</label><input type="date" name="allocation_date" class="form-control" value="{{ old('allocation_date',optional($record->allocation_date)->format('Y-m-d')) }}" required></div>
</div>
<div class="dashboard-card"><h3>Budget Utilization Panel</h3>
<p>Available Fund: <strong id="availPreview">—</strong></p><p>Proposed Allocation: <strong id="propPreview">—</strong></p><p>Remaining After: <strong id="afterPreview">—</strong></p>
<div class="form-group"><label>Purpose</label><input type="text" name="purpose" class="form-control" value="{{ old('purpose',$record->purpose) }}" required></div>
<div class="form-group"><label>Justification / Description</label><textarea name="description" class="form-control" rows="3">{{ old('description',$record->description) }}</textarea></div>
<div class="form-group"><label>Supporting Document</label><input type="file" name="supporting_document" class="form-control"></div>
<div class="form-group"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks',$record->remarks) }}</textarea></div>
</div>
</div>
<div style="margin-top:12px;display:flex;gap:8px;"><button name="action" value="draft" class="btn btn-secondary">Save Draft</button>@if(!isset($record->id))<button name="action" value="submit" class="btn btn-primary">Save &amp; Submit for Admin Approval</button>@else<button class="btn btn-primary">Save Revision as Draft</button>@endif</div>
</form>
@push('scripts')<script>
function upd(){const s=document.getElementById('fundSelect'),a=parseFloat(s.selectedOptions[0].dataset.avail)||0,p=parseFloat(document.getElementById('allocAmount').value)||0;document.getElementById('availPreview').textContent='₱'+a.toLocaleString('en-PH',{minimumFractionDigits:2});document.getElementById('propPreview').textContent='₱'+p.toLocaleString('en-PH',{minimumFractionDigits:2});document.getElementById('afterPreview').textContent='₱'+(a-p).toLocaleString('en-PH',{minimumFractionDigits:2});}
document.getElementById('fundSelect').addEventListener('change',upd);document.getElementById('allocAmount').addEventListener('input',upd);upd();
// Budget ID lookup + detail panel — para matrack kung magkano ang budget bago mag-set ng amount.
const bSel=document.getElementById('budgetSelect'),bLook=document.getElementById('budget_lookup'),bBtn=document.getElementById('budget_load'),bMsg=document.getElementById('budget-msg'),bBox=document.getElementById('budget-preview'),bLabel=document.getElementById('bp-label'),bLines=document.getElementById('bp-lines');
function peso(n){return '₱'+(parseFloat(n)||0).toLocaleString('en-PH',{minimumFractionDigits:2});}
function renderBudget(){
  const o=bSel.options[bSel.selectedIndex];
  if(!o || !o.value){bBox.style.display='none';return;}
  bBox.style.display='block';
  const alloc=parseFloat(o.dataset.alloc)||0,used=parseFloat(o.dataset.used)||0,rem=parseFloat(o.dataset.rem)||0;
  bLabel.textContent='Budget #'+o.value+' — '+o.text.split(' (')[0].replace(/^#\d+ — /,'');
  bLines.innerHTML='Allocated: <strong>'+peso(alloc)+'</strong><br>Used/Committed: <strong>'+peso(used)+'</strong><br>Remaining: <strong>'+peso(rem)+'</strong><br><span class="summary-desc">Iyan ang pinakamalaking puwedeng i-set. Ang server ay haharang kapag lumampas sa available fund.</span>';
}
function loadBudgetById(auto){
  const q=bLook.value.trim();
  if(!q){if(!auto)bMsg.textContent='Maglagay muna ng Budget ID.';return;}
  let matched=false;
  for(const o of bSel.options){if(o.value===q){bSel.value=q;matched=true;break;}}
  if(matched){bMsg.textContent='Nahanap: Budget #'+q+'.';renderBudget();}
  else{bMsg.textContent=auto?'':'Walang approved budget na may ID "'+q+'".';if(auto)bBox.style.display='none';}
}
bSel.addEventListener('change',function(){bLook.value=bSel.value;renderBudget();});
bBtn.addEventListener('click',function(){loadBudgetById(false);});
bLook.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();loadBudgetById(false);}});
let bDeb=null;
bLook.addEventListener('input',function(){clearTimeout(bDeb);const q=bLook.value.trim();if(!q){bMsg.textContent='';return;}bDeb=setTimeout(function(){loadBudgetById(true);},600);});
renderBudget();
</script>@endpush
@endsection
