@extends('layouts.app')
@section('title', 'Fund Management and Allocation')
@section('content')
<div class="page-header"><div><h2>Fund Management and Allocation</h2><p class="page-subtitle">Monitoring of fund sources and automatically created budget allocations. Approved Budget → Allocation → Expense.</p></div></div>

<div class="dashboard-card">
<div class="card-head-row"><h3>Fund Summary</h3></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Fund</th><th style="text-align:right;">Total Fund</th><th style="text-align:right;">Allocated</th><th style="text-align:right;">Reserved</th><th style="text-align:right;">Available Fund</th></tr></thead><tbody>
@forelse($funds as $f)<tr><td><strong>{{ $f->fund_name }}</strong><br><span class="summary-desc">{{ $f->status }}</span></td><td style="text-align:right;">₱{{ number_format((float) $f->initial_balance,2) }}</td><td style="text-align:right;">₱{{ number_format((float) $f->used_amount,2) }}</td><td style="text-align:right;">₱{{ number_format((float) $f->reserved_amount,2) }}</td><td style="text-align:right;"><strong>₱{{ number_format((float) $f->available_amount,2) }}</strong></td></tr>
@empty<tr><td colspan="5" class="text-center muted-text">No funds</td></tr>@endforelse
</tbody></table></div></div>

<div class="dashboard-card" style="margin-top:16px;">
<div class="card-head-row"><h3>Approved Budget Allocations</h3></div>
<p class="page-subtitle">Auto-created when Admin approves a Budget Request. No manual re-entry — new allocations start from <a href="{{ route('accountant.budgets.requests') }}">Budget Requests</a>.</p>
<form method="GET" class="filter-form" style="align-items:flex-end;">
<div class="form-group" style="min-width:170px;"><label>Fund Source</label><select name="fund" class="form-control"><option value="">All funds</option>@foreach($funds as $f)<option value="{{ $f->fund_name }}" {{ request('fund')===$f->fund_name?'selected':'' }}>{{ $f->fund_name }}</option>@endforeach</select></div>
<div class="form-group" style="min-width:170px;"><label>Department</label><select name="department" class="form-control"><option value="">All departments</option>@foreach(($departments ?? []) as $d)<option value="{{ $d }}" {{ request('department')===$d?'selected':'' }}>{{ $d }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;"><button class="btn btn-secondary">Filter</button><a href="{{ route('accountant.fund-allocations.index') }}" class="btn btn-secondary">Clear</a></div>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>Allocation ID</th><th>Department</th><th>Fund Source</th><th>Academic Year</th><th>Category</th><th style="text-align:right;">Approved</th><th style="text-align:right;">Allocated</th><th style="text-align:right;">Utilized</th><th style="text-align:right;">Remaining</th><th>Status</th></tr></thead><tbody>
@forelse($allocations as $a)
@php $b = $a->budgetPlan; @endphp
<tr>
<td><strong>{{ $a->allocation_id }}</strong></td>
<td>{{ $b->department ?? $a->allocated_to }}</td>
<td>{{ $b->funding_source ?? '—' }}</td>
<td>{{ $b->academic_year ?? '—' }}</td>
<td>{{ $b->budget_category ?? '—' }}</td>
<td style="text-align:right;">₱{{ number_format($b->approved_amount_value ?? (float) $a->amount,2) }}</td>
<td style="text-align:right;font-weight:700;">₱{{ number_format((float) $a->amount,2) }}</td>
<td style="text-align:right;">₱{{ number_format((float) ($b->utilized_amount ?? 0),2) }}</td>
<td style="text-align:right;">₱{{ number_format((float) ($b->remaining_amount ?? $a->amount),2) }}</td>
<td><span class="badge badge-green">Active</span></td>
</tr>
@empty<tr><td colspan="10" class="text-center muted-text">No approved allocations yet. They appear here automatically after Admin approval.</td></tr>
@endforelse</tbody></table></div>
{{ $allocations->links() }}</div>
@endsection
