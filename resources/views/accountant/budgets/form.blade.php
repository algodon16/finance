@extends('layouts.app')
@section('title', isset($plan->id) ? 'Edit Budget Request' : 'Create Budget Request')
@section('content')
<style>
.bp-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;}
.bp-info-grid .form-group{margin-bottom:0;}
.bp-date-row{grid-column:1/-1;display:grid;grid-template-columns:repeat(2,180px);gap:12px;justify-content:start;}
.bp-date-row .form-control{max-width:180px;padding:6px 8px;font-size:.85rem;}
.bp-total-bar{display:flex;justify-content:space-between;align-items:center;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-top:12px;flex-wrap:wrap;gap:8px;}
.bp-total-bar strong{font-size:1.25rem;}
.type-details{display:none;}
.type-details.open{display:block;}
@media(max-width:900px){.bp-info-grid{grid-template-columns:1fr;}.bp-date-row{grid-template-columns:1fr;}.bp-date-row .form-control{max-width:100%;}}
</style>

<div class="page-header"><div><h2>{{ isset($plan->id) ? 'Edit Budget Request' : 'Create Budget Request' }}</h2><p class="page-subtitle">Department budget proposal — review, approval, fund allocation, and utilization follow in their respective modules.</p></div><a href="{{ route('accountant.budgets.requests') }}" class="btn btn-secondary">Back</a></div>
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

@php
$selType = old('request_type', $plan->request_type ?? 'department_operating');
$details = old('request_details', $plan->request_details ?? []);
$funds = $funds ?? collect();
@endphp
<form method="POST" action="{{ isset($plan->id) ? route('accountant.budgets.update',$plan) : route('accountant.budgets.store') }}" enctype="multipart/form-data" id="budgetPlanForm">
@csrf @if(isset($plan->id)) @method('PUT') @endif

{{-- ============ REQUEST INFORMATION ============ --}}
<div class="dashboard-card">
<h3>Request Information</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Budget Purpose <span class="required">*</span></label><input type="text" name="budget_name" class="form-control" value="{{ old('budget_name',$plan->budget_name) }}" placeholder="e.g. Academic Operations 2026-2027" required></div>
<div class="form-group"><label>Department / Office <span class="required">*</span></label>@if(($departments ?? collect())->count())<select name="department" class="form-control" required><option value="">Select Department</option>@foreach($departments as $d)<option value="{{ $d }}" {{ old('department',$plan->department)===$d?'selected':'' }}>{{ $d }}</option>@endforeach</select>@else<input type="text" name="department" class="form-control" value="{{ old('department',$plan->department) }}" required>@endif</div>
<div class="form-group"><label>Academic Year <span class="required">*</span></label>@if(($academicYears ?? collect())->count())<select name="academic_year" class="form-control" required><option value="">Select Year</option>@foreach($academicYears as $y)<option value="{{ $y }}" {{ old('academic_year',$plan->academic_year)===$y?'selected':'' }}>{{ $y }}</option>@endforeach</select>@else<input type="text" name="academic_year" class="form-control" value="{{ old('academic_year',$plan->academic_year) }}" required>@endif</div>
<div class="form-group"><label>Request Type <span class="required">*</span></label><select name="request_type" id="requestType" class="form-control" required>@foreach(\App\Models\BudgetPlan::REQUEST_TYPES as $k=>$l)<option value="{{ $k }}" {{ $selType===$k?'selected':'' }}>{{ $l }}</option>@endforeach</select></div>
<div class="bp-date-row">
<div class="form-group"><label>Budget Period Start <span class="required">*</span></label><input type="date" name="start_date" class="form-control" value="{{ old('start_date',optional($plan->start_date)->format('Y-m-d')) }}" required></div>
<div class="form-group"><label>Budget Period End <span class="required">*</span></label><input type="date" name="end_date" class="form-control" value="{{ old('end_date',optional($plan->end_date)->format('Y-m-d')) }}" required></div>
</div>
<div class="form-group" style="grid-column:1/-1;"><label>Fund Source <span class="required">*</span></label>@if($funds->count())<select name="funding_source" class="form-control" required><option value="">Select Fund</option>@foreach($funds as $f)<option value="{{ $f->fund_name }}" {{ old('funding_source',$plan->funding_source)===$f->fund_name?'selected':'' }}>{{ $f->fund_name }} (Avail ₱{{ number_format((float) $f->available_amount,2) }})</option>@endforeach</select>@else<input type="text" name="funding_source" class="form-control" value="{{ old('funding_source',$plan->funding_source) }}" required>@endif<p class="summary-desc">Assigned at request time and consumed by Fund Management after approval. Requested amount must not exceed available fund.</p></div>
<div class="form-group" style="grid-column:1/-1;"><label>Requested Budget Amount (₱) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="requested_amount" id="requestedAmount" class="form-control" value="{{ old('requested_amount',$plan->requested_amount_value) }}" placeholder="e.g. 500000.00" required></div>
<div class="form-group" style="grid-column:1/-1;"><label>Justification / Description <span class="required">*</span></label><textarea name="justification" class="form-control" rows="3" placeholder="Explain why the department needs the requested budget..." required>{{ old('justification',$plan->justification) }}</textarea></div>
<div class="form-group" style="grid-column:1/-1;"><label>Supporting Document (pdf/jpg/png, 5MB, optional)</label><input type="file" name="supporting_document" class="form-control">@if(!empty($plan->supporting_document))<p><a href="{{ asset('storage/'.$plan->supporting_document) }}" target="_blank">Current document</a></p>@endif</div>
</div>
</div>

{{-- ============ TYPE DETAILS (dynamic) ============ --}}
<div class="dashboard-card type-details" style="margin-top:16px;" id="det-payroll_personnel">
<h3>Payroll / Personnel Details</h3>
<p class="page-subtitle">Salary rates remain sourced from Academic HR — Financial Management records only the budget requirement.</p>
<div class="bp-info-grid">
<div class="form-group"><label>Personnel Category</label><select name="request_details[personnel_category]" class="form-control"><option value="">Select</option>@foreach(['Teaching','Non-Teaching','COS / Contractual','Overtime Pool'] as $c)<option value="{{ $c }}" {{ ($details['personnel_category'] ?? '')===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select></div>
<div class="form-group"><label>Payroll Period</label><input type="text" name="request_details[payroll_period]" class="form-control" value="{{ $details['payroll_period'] ?? '' }}" placeholder="e.g. June 2026"></div>
<div class="form-group"><label>Overtime / Additional Personnel Cost (₱)</label><input type="number" step="0.01" min="0" name="request_details[overtime_amount]" class="form-control" value="{{ $details['overtime_amount'] ?? '' }}" placeholder="0.00"></div>
</div>
</div>

<div class="dashboard-card type-details" style="margin-top:16px;" id="det-program_activity">
<h3>Program / Activity Details</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Activity / Program Name</label><input type="text" name="request_details[activity_name]" class="form-control" value="{{ $details['activity_name'] ?? '' }}"></div>
<div class="form-group"><label>Activity Date or Period</label><input type="text" name="request_details[activity_date]" class="form-control" value="{{ $details['activity_date'] ?? '' }}"></div>
<div class="form-group"><label>Estimated Participants</label><input type="number" min="0" name="request_details[participants]" class="form-control" value="{{ $details['participants'] ?? '' }}"></div>
</div>
</div>

<div class="dashboard-card type-details" style="margin-top:16px;" id="det-project">
<h3>Project Details</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Project Name</label><input type="text" name="request_details[project_name]" class="form-control" value="{{ $details['project_name'] ?? '' }}"></div>
<div class="form-group"><label>Project Period</label><input type="text" name="request_details[project_period]" class="form-control" value="{{ $details['project_period'] ?? '' }}"></div>
<div class="form-group" style="grid-column:1/-1;"><label>Project Description</label><textarea name="request_details[project_description]" class="form-control" rows="2">{{ $details['project_description'] ?? '' }}</textarea></div>
</div>
</div>

<div class="dashboard-card type-details" style="margin-top:16px;" id="det-maintenance_repair">
<h3>Maintenance / Repair Details</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Property / Equipment</label><input type="text" name="request_details[property_equipment]" class="form-control" value="{{ $details['property_equipment'] ?? '' }}"></div>
<div class="form-group"><label>Service Type</label><select name="request_details[service_type]" class="form-control"><option value="">Select</option>@foreach(['Preventive','Corrective','Emergency'] as $c)<option value="{{ $c }}" {{ ($details['service_type'] ?? '')===$c?'selected':'' }}>{{ $c }}</option>@endforeach</select></div>
<div class="form-group"><label>Schedule / Period</label><input type="text" name="request_details[schedule]" class="form-control" value="{{ $details['schedule'] ?? '' }}"></div>
</div>
</div>

<div class="dashboard-card type-details" style="margin-top:16px;" id="det-research_grant">
<h3>Research / Grant Details</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Research / Grant Title</label><input type="text" name="request_details[research_title]" class="form-control" value="{{ $details['research_title'] ?? '' }}"></div>
<div class="form-group"><label>Research Period</label><input type="text" name="request_details[research_period]" class="form-control" value="{{ $details['research_period'] ?? '' }}"></div>
<div class="form-group"><label>Researcher / Responsible Unit</label><input type="text" name="request_details[researcher_unit]" class="form-control" value="{{ $details['researcher_unit'] ?? '' }}"></div>
</div>
</div>

<div class="dashboard-card type-details" style="margin-top:16px;" id="det-emergency_contingency">
<h3>Emergency / Contingency Details</h3>
<div class="bp-info-grid">
<div class="form-group"><label>Emergency Description</label><input type="text" name="request_details[emergency_description]" class="form-control" value="{{ $details['emergency_description'] ?? '' }}"></div>
<div class="form-group"><label>Date / Period</label><input type="text" name="request_details[incident_date]" class="form-control" value="{{ $details['incident_date'] ?? '' }}"></div>
</div>
</div>

<div class="dashboard-card type-details" style="margin-top:16px;" id="det-fund_reallocation">
<h3>Fund Transfer / Reallocation Details</h3>
<p class="page-subtitle">The transfer follows the approval workflow — nothing moves until approved and allocated.</p>
<div class="bp-info-grid">
<div class="form-group"><label>Source Department / Budget</label>@if(($departments ?? collect())->count())<select name="request_details[source_department]" class="form-control"><option value="">Select</option>@foreach($departments as $d)<option value="{{ $d }}" {{ ($details['source_department'] ?? '')===$d?'selected':'' }}>{{ $d }}</option>@endforeach</select>@else<input type="text" name="request_details[source_department]" class="form-control" value="{{ $details['source_department'] ?? '' }}">@endif</div>
<div class="form-group"><label>Amount (₱)</label><input type="number" step="0.01" min="0" name="request_details[transfer_amount]" class="form-control" value="{{ $details['transfer_amount'] ?? '' }}"></div>
<div class="form-group" style="grid-column:1/-1;"><label>Reason for Reallocation</label><textarea name="request_details[transfer_reason]" class="form-control" rows="2">{{ $details['transfer_reason'] ?? '' }}</textarea></div>
</div>
</div>

{{-- ============ BUDGET BREAKDOWN ============ --}}
<div class="dashboard-card" style="margin-top:16px;">
<div class="card-head-row"><h3>Budget Breakdown</h3><button type="button" class="btn btn-sm btn-secondary" id="addItemBtn">+ Add Budget Item</button></div>
<p class="page-subtitle" id="breakdownHint">What the requested amount is intended for. The item total must equal the Requested Budget Amount on submit.</p>
<div class="table-responsive"><table class="table" id="itemsTable"><thead><tr><th>Category</th><th>Description</th><th class="qty-col" style="display:none;">Qty</th><th class="qty-col" style="display:none;">Unit Cost (₱)</th><th style="text-align:right;">Amount (₱)</th><th>Action</th></tr></thead><tbody id="itemsBody">
@php
$oldItems = old('items');
if (! is_array($oldItems)) {
    $oldItems = (isset($plan->id) && $plan->items->count())
        ? $plan->items->map(fn($it) => ['category' => $it->category, 'description' => $it->item_name, 'quantity' => $it->quantity, 'unit_cost' => $it->unit_cost, 'amount' => $it->line_total])->all()
        : [['category' => '', 'description' => '', 'quantity' => 1, 'unit_cost' => '', 'amount' => '']];
}
@endphp
@foreach($oldItems as $idx=>$it)
<tr class="item-row">
<td><select name="items[{{ $idx }}][category]" class="form-control item-cat" required><option value="">Select</option></select></td>
<td><input type="text" name="items[{{ $idx }}][description]" class="form-control" value="{{ $it['description'] ?? '' }}" placeholder="Description" required></td>
<td class="qty-col" style="display:none;"><input type="number" min="1" step="1" name="items[{{ $idx }}][quantity]" class="form-control item-qty" value="{{ $it['quantity'] ?? 1 }}"></td>
<td class="qty-col" style="display:none;"><input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_cost]" class="form-control item-unit" style="text-align:right;" value="{{ $it['unit_cost'] ?? '' }}"></td>
<td><input type="number" step="0.01" min="0.01" name="items[{{ $idx }}][amount]" class="form-control item-amount" style="text-align:right;" value="{{ $it['amount'] ?? '' }}" placeholder="0.00" required></td>
<td><button type="button" class="btn btn-sm btn-secondary remove-item">Remove</button></td>
</tr>
@endforeach
</tbody></table></div>
<div class="bp-total-bar"><div>Total Proposed Budget: <strong id="itemsTotal">₱0.00</strong></div><div><span class="summary-desc" id="itemsCount">0 items</span></div></div>
<p class="summary-desc" id="matchMsg" style="margin-top:6px;"></p>
</div>

{{-- ============ SUMMARY ============ --}}
<div class="dashboard-card" style="margin-top:16px;">
<h3>Budget Request Summary</h3>
<p>Requested Budget: <strong id="sumRequested">₱{{ number_format((float) old('requested_amount',$plan->requested_amount_value ?? 0),2) }}</strong></p>
<p>Budget Items: <strong id="sumItems">0 items</strong></p>
<p>Department: <strong id="sumDept">{{ old('department',$plan->department) ?: '—' }}</strong></p>
<p>Academic Year: <strong id="sumYear">{{ old('academic_year',$plan->academic_year) ?: '—' }}</strong></p>
<p>Status: <strong>{{ isset($plan->id) ? ucfirst($plan->status) : 'Draft' }}</strong></p>
</div>

<div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end;">
<button name="action" value="submit" class="btn btn-primary" style="min-width:180px;">Submit for Review</button>
</div>
@if(isset($plan->id))<p class="page-subtitle" style="text-align:right;margin-top:6px;">Items can only be changed while the request is still editable (draft / revision).</p>@endif
</form>

@push('scripts')<script>
let itemIdx = {{ count($oldItems) }};
const CATS = @json(\App\Models\BudgetPlan::TYPE_CATEGORIES);
const QTY_TYPES = @json(\App\Models\BudgetPlan::QTY_TYPES);
const OLD_CATS = @json(collect($oldItems)->pluck('category'));
const peso = (n) => '₱' + (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const typeSel = document.getElementById('requestType');
function currentType() { return typeSel.value; }
function fillCats(sel, keep) {
    const list = CATS[currentType()] || CATS['other'];
    sel.innerHTML = '<option value="">Select</option>' + list.map((c) => '<option value="' + c + '"' + (keep === c ? ' selected' : '') + '>' + c + '</option>').join('');
}
function applyType() {
    const t = currentType();
    document.querySelectorAll('.type-details').forEach((el) => el.classList.remove('open'));
    const det = document.getElementById('det-' + t);
    if (det) det.classList.add('open');
    const qty = QTY_TYPES.includes(t);
    document.querySelectorAll('.qty-col').forEach((el) => { el.style.display = qty ? '' : 'none'; });
    document.querySelectorAll('#itemsBody .item-row').forEach((row, i) => fillCats(row.querySelector('.item-cat'), OLD_CATS[i]));
    OLD_CATS.length = 0;
    document.getElementById('breakdownHint').textContent = qty
        ? 'Item / quantity breakdown. Line total = Qty × Unit Cost. The item total must equal the Requested Budget Amount on submit.'
        : 'What the requested amount is intended for. The item total must equal the Requested Budget Amount on submit.';
    recalc();
}
function rowTotal(row) {
    if (QTY_TYPES.includes(currentType())) {
        const q = parseFloat(row.querySelector('.item-qty').value) || 0;
        const u = parseFloat(row.querySelector('.item-unit').value) || 0;
        return q * u;
    }
    return parseFloat(row.querySelector('.item-amount').value) || 0;
}
function recalc() {
    let total = 0, count = 0;
    const qty = QTY_TYPES.includes(currentType());
    document.querySelectorAll('#itemsBody .item-row').forEach((row) => {
        let line = 0;
        if (qty) {
            const q = parseFloat(row.querySelector('.item-qty').value) || 0;
            const u = parseFloat(row.querySelector('.item-unit').value) || 0;
            line = q * u;
            row.querySelector('.item-amount').value = line ? line.toFixed(2) : '';
        } else {
            line = parseFloat(row.querySelector('.item-amount').value) || 0;
        }
        total += line; count++;
    });
    const req = parseFloat(document.getElementById('requestedAmount').value) || 0;
    document.getElementById('itemsTotal').textContent = peso(total);
    document.getElementById('itemsCount').textContent = count + (count === 1 ? ' item' : ' items');
    document.getElementById('sumRequested').textContent = peso(req);
    document.getElementById('sumItems').textContent = count + (count === 1 ? ' item' : ' items');
    const dept = document.querySelector('[name="department"]');
    document.getElementById('sumDept').textContent = dept && dept.value ? dept.value : '—';
    const yr = document.querySelector('[name="academic_year"]');
    document.getElementById('sumYear').textContent = yr && yr.value ? yr.value : '—';
    const msg = document.getElementById('matchMsg');
    if (count === 0) { msg.textContent = 'At least one budget item is required.'; msg.style.color = '#b91c1c'; }
    else if (Math.abs(total - req) > 0.009) { msg.textContent = 'Breakdown total (' + peso(total) + ') must equal Requested Budget (' + peso(req) + ') before submitting.'; msg.style.color = '#b91c1c'; }
    else { msg.textContent = 'Breakdown matches the requested amount.'; msg.style.color = '#047857'; }
}
document.getElementById('addItemBtn').addEventListener('click', () => {
    const i = itemIdx++;
    const tr = document.createElement('tr');
    tr.className = 'item-row';
    tr.innerHTML = '<td><select name="items[' + i + '][category]" class="form-control item-cat" required><option value="">Select</option></select></td>' +
        '<td><input type="text" name="items[' + i + '][description]" class="form-control" placeholder="Description" required></td>' +
        '<td class="qty-col" style="display:none;"><input type="number" min="1" step="1" name="items[' + i + '][quantity]" class="form-control item-qty" value="1"></td>' +
        '<td class="qty-col" style="display:none;"><input type="number" step="0.01" min="0" name="items[' + i + '][unit_cost]" class="form-control item-unit" style="text-align:right;" placeholder="0.00"></td>' +
        '<td><input type="number" step="0.01" min="0.01" name="items[' + i + '][amount]" class="form-control item-amount" style="text-align:right;" placeholder="0.00" required></td>' +
        '<td><button type="button" class="btn btn-sm btn-secondary remove-item">Remove</button></td>';
    document.getElementById('itemsBody').appendChild(tr);
    fillCats(tr.querySelector('.item-cat'), '');
    applyType(); recalc();
});
document.getElementById('itemsBody').addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-item')) { e.target.closest('.item-row').remove(); recalc(); }
});
document.getElementById('itemsBody').addEventListener('input', recalc);
document.getElementById('requestedAmount').addEventListener('input', recalc);
document.querySelector('[name="department"]').addEventListener('change', recalc);
document.querySelector('[name="academic_year"]').addEventListener('change', recalc);
typeSel.addEventListener('change', applyType);
document.getElementById('budgetPlanForm').addEventListener('submit', (e) => {
    let total = 0, count = 0;
    document.querySelectorAll('#itemsBody .item-row').forEach((row) => { total += rowTotal(row); count++; });
    const req = parseFloat(document.getElementById('requestedAmount').value) || 0;
    if (count === 0 || Math.abs(total - req) > 0.009) {
        e.preventDefault();
        alert('Budget breakdown total must equal the Requested Budget Amount before submitting.');
    }
});
applyType(); recalc();
</script>@endpush
@endsection
