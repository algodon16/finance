@extends('layouts.app')
@section('title', 'Prepare Payable')
@section('content')
<div class="page-header"><div><h2>{{ isset($record->id) ? 'Revise Payable' : 'Prepare Payable' }}</h2><p class="page-subtitle">Select an approved request — vendor, department and amounts fill in automatically.</p></div><a href="{{ route('accountant.payables.index') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@php
  $selKind = old('source_kind', $record->expense_id ? 'expense' : ($record->financial_request_id ? 'financial_request' : 'expense'));
  $selId = old('source_id', $record->expense_id ?? $record->financial_request_id ?? '');
@endphp
<form method="POST" action="{{ isset($record->id) ? route('accountant.payables.update',$record) : route('accountant.payables.store') }}" enctype="multipart/form-data" id="payable-form">
@csrf @if(isset($record->id)) @method('PUT') @endif

<div class="dashboard-card">
  <h3>Related Transaction</h3>
  <div class="form-group">
    <label for="source_lookup">Financial Request / Expense ID * <span class="summary-desc">— i-type ang ID o request number (hal. 6 o FR-20260929-XXXX), kusang lalabas ang detalye</span></label>
    <div style="display:flex;gap:8px;">
      <input type="text" id="source_lookup" class="form-control" placeholder="ID o request number…" autocomplete="off" value="{{ $selId }}">
      <button type="button" id="source_load" class="btn btn-secondary" style="white-space:nowrap;">Load</button>
    </div>
    <small class="summary-desc" id="lookup-msg"></small>
    <input type="hidden" name="source_kind" id="source_kind" value="{{ $selKind }}">
    <input type="hidden" name="source_id" id="source_id" value="{{ $selId }}">
  </div>
  <div id="source-preview" class="dashboard-card" style="background:#f8fafc;margin-top:12px;display:none;">
    <p style="margin:0 0 4px;"><strong id="pv-label">—</strong></p>
    <p style="margin:0;" id="pv-lines">Ilagay ang ID sa itaas para lumabas ang detalye.</p>
  </div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
  <h3>Payable Information</h3>
  <div class="two-col-grid">
    <div class="form-group"><label>Invoice Number *</label><input type="text" name="invoice_number" class="form-control" value="{{ old('invoice_number',$record->invoice_number) }}" required maxlength="100"></div>
    <div class="form-group"><label>Payment Terms *</label>
      <select name="payment_terms" class="form-control" required>
        @foreach(['COD','15 Days','30 Days','60 Days','Custom'] as $t)
          <option value="{{ $t }}" {{ old('payment_terms',$record->payment_terms ?? '30 Days') === $t ? 'selected' : '' }}>{{ $t }}</option>
        @endforeach
      </select>
    </div>
    <div class="form-group"><label>Invoice Date *</label><input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date',optional($record->invoice_date)->format('Y-m-d')) }}" required max="{{ date('Y-m-d') }}"></div>
    <div class="form-group"><label>Due Date *</label><input type="date" name="due_date" id="due_date" class="form-control" value="{{ old('due_date',optional($record->due_date)->format('Y-m-d')) }}" required></div>
  </div>
  <div class="form-group"><label>Total Amount (₱) *</label><input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" value="{{ old('amount',$record->amount) }}" required><small class="summary-desc" id="amount-hint">Auto-filled from the approved source amount. It cannot exceed that amount.</small></div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
  <h3>Supporting Document</h3>
  <div class="form-group"><label>Invoice Attachment (PDF / JPG / PNG, max 5 MB)</label><input type="file" name="supporting_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">@if(!empty($record->supporting_document))<small class="summary-desc">Current: <a href="{{ asset('storage/'.$record->supporting_document) }}" target="_blank">View file</a> — uploading replaces it.</small>@endif</div>
  <div class="form-group"><label>Remarks (optional)</label><textarea name="remarks" class="form-control" rows="2" maxlength="2000" placeholder="Short note, e.g. partial billing">{{ old('remarks',$record->remarks) }}</textarea></div>
</div>

<div style="margin-top:12px;display:flex;gap:8px;">
  <button name="action" value="draft" class="btn btn-secondary">Save Draft</button>
  @if(!isset($record->id))<button name="action" value="submit" class="btn btn-primary">Submit for Approval</button>
  @else<button class="btn btn-primary">Save Revision as Draft</button>@endif
</div>
</form>

<script>
(function(){
  const kindEl = document.getElementById('source_kind');
  const idEl = document.getElementById('source_id');
  const box = document.getElementById('source-preview');
  const label = document.getElementById('pv-label');
  const lines = document.getElementById('pv-lines');
  const amount = document.getElementById('amount');
  const hint = document.getElementById('amount-hint');
  const previewUrl = "{{ route('accountant.payables.source-preview') }}";
  let firstLoad = true;

  function renderPreview(d){
    box.style.display='block';
    label.textContent = 'Request: ' + d.label;
    lines.innerHTML = 'Supplier: <strong>' + (d.vendor||'—') + '</strong><br>Department: <strong>' + (d.department||'—') + '</strong><br>Approved Amount: <strong>₱' + d.approved_amount + '</strong><br>Budget Reference: <strong>' + (d.budget_reference||'—') + '</strong>' + (d.allocation_reference && d.allocation_reference !== '—' ? '<br>Fund Allocation: <strong>' + d.allocation_reference + '</strong>' : '');
    if(firstLoad && amount.value){ /* keep existing value on edit/validation-error */ }
    else { amount.value = d.approved_amount_raw; }
    amount.max = d.approved_amount_raw;
    hint.textContent = 'Auto-filled from ' + d.label + ' (₱' + d.approved_amount + '). It cannot exceed that amount.';
  }

  // ID / request-number lookup — isang automation lang, walang dropdown.
  const lookup = document.getElementById('source_lookup');
  const loadBtn = document.getElementById('source_load');
  const lookupMsg = document.getElementById('lookup-msg');
  let lastQuery = '';
  async function loadById(auto){
    const q = lookup.value.trim();
    if(!q){ if(!auto) lookupMsg.textContent = 'Maglagay muna ng ID o request number.'; return; }
    if(auto && q === lastQuery) return; // huwag ulitin ang parehong query
    lastQuery = q;
    lookupMsg.textContent = 'Hinahanap…';
    try{
      const res = await fetch(previewUrl + '?q=' + encodeURIComponent(q), {headers:{'Accept':'application/json'}});
      if(res.status === 404){ lookupMsg.textContent = auto ? '' : 'Walang approved na request na tumutugma sa "' + q + '".'; if(auto){ box.style.display='none'; } return; }
      if(!res.ok) throw new Error('lookup failed');
      const d = await res.json();
      firstLoad = false;
      kindEl.value = d.kind; idEl.value = d.id;
      renderPreview(d);
      lookupMsg.textContent = 'Nahanap: ' + d.label + '. Supplier, amount at lahat ng detalye ay auto-filled na.';
    }catch(e){ if(!auto) lookupMsg.textContent = 'Hindi ma-load ang preview. Subukang muli.'; }
  }
  loadBtn.addEventListener('click', function(){ loadById(false); });
  lookup.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); loadById(false); } });
  // Awtomatikong maghanap habang tina-type — lalabas agad ang supplier/invoice preview.
  let debounce = null;
  lookup.addEventListener('input', function(){
    clearTimeout(debounce);
    const q = lookup.value.trim();
    if(!q){ lastQuery = ''; lookupMsg.textContent = ''; return; }
    debounce = setTimeout(function(){ loadById(true); }, 600);
  });
  // Sa edit page: i-load agad ang naka-link nang source.
  if(kindEl.value && idEl.value){
    lookup.value = lookup.value || idEl.value;
    (async function(){
      try{
        const res = await fetch(previewUrl + '?kind=' + encodeURIComponent(kindEl.value) + '&id=' + encodeURIComponent(idEl.value), {headers:{'Accept':'application/json'}});
        if(res.ok){ renderPreview(await res.json()); }
      }catch(e){}
      firstLoad = false;
    })();
  }
  document.getElementById('payable-form').addEventListener('submit', function(e){
    if(!kindEl.value || !idEl.value){ e.preventDefault(); alert('Ilagay muna ang Financial Request / Expense ID.'); }
  });
})();
</script>
@endsection
