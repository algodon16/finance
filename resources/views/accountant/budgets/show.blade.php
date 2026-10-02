@extends('layouts.app')
@section('title', 'Budget Plan Details')
@section('content')
<div class="page-header"><div><h2>{{ $plan->budget_name }}</h2><p class="page-subtitle">Rev {{ $plan->revision_number ?? 0 }} | @include('accountant.partials.status-badge',['status'=>$plan->status]) | Prepared by {{ $plan->creator->name ?? 'N/A' }} on {{ $plan->created_at?->format('M d, Y') }} | Submitted by {{ $plan->submitter->name ?? '—' }} | Reviewed by {{ $plan->reviewer->name ?? $plan->approver->name ?? '—' }}</p></div><a href="{{ route('accountant.budgets.index') }}" class="btn btn-secondary">Back</a></div>
@if($plan->rejection_reason)<div class="alert alert-error"><strong>Admin decision:</strong> {{ $plan->rejection_reason }}<br><strong>Admin remarks:</strong> {{ $plan->admin_remarks }}<br><span class="summary-desc">Revise and resubmit — history below is preserved.</span></div>@endif
@if($plan->admin_remarks && !$plan->rejection_reason)<div class="dashboard-card"><strong>Admin remarks:</strong> {{ $plan->admin_remarks }}</div>@endif
<div class="two-col-grid">
<div class="dashboard-card"><h3>Plan Details</h3>
<p><strong>Request ID:</strong> {{ $plan->request_id }}</p>
<p><strong>Request Type:</strong> {{ $plan->request_type_label }}</p>
<p><strong>Academic Year:</strong> {{ $plan->academic_year }}</p>
<p><strong>Requested:</strong> ₱{{ number_format($plan->requested_amount_value,2) }} | <strong>Proposed:</strong> {{ $plan->proposed_amount ? '₱'.number_format((float) $plan->proposed_amount,2) : '—' }} | <strong>Approved:</strong> {{ $plan->approved_amount ? '₱'.number_format((float) $plan->approved_amount,2) : '—' }} | <strong>Allocated:</strong> ₱{{ number_format((float) $plan->allocated_amount,2) }}</p>
@if($plan->accountant_remarks)<p><strong>Accountant Remarks:</strong> {{ $plan->accountant_remarks }}</p>@endif
@if($plan->allocation_recommendation)<p><strong>Allocation Recommendation:</strong> {{ \App\Models\BudgetPlan::RECOMMENDATIONS[$plan->allocation_recommendation] ?? $plan->allocation_recommendation }}</p>@endif
@if($plan->documents_verified_flag)<p><strong>Documents Verified:</strong> Yes</p>@endif
<p><strong>Budget Period Start:</strong> {{ optional($plan->start_date)->format('M d, Y') ?? '—' }} | <strong>Budget Period End:</strong> {{ optional($plan->end_date)->format('M d, Y') ?? '—' }}</p>
<p><strong>Department:</strong> {{ $plan->department }} | <strong>Category:</strong> {{ $plan->budget_category }} | <strong>Fund Source:</strong> {{ $plan->funding_source }}</p>
<p><strong>Description:</strong> {{ $plan->description ?: '—' }}</p>
<p><strong>Justification:</strong> {{ $plan->justification ?: '—' }}</p>
@if($plan->items->count())
<h4 style="margin-top:10px;">Budget Breakdown</h4>
<div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th>Description</th><th style="text-align:right;">Amount</th></tr></thead><tbody>
@foreach($plan->items as $it)<tr><td>{{ $it->category ?? '—' }}</td><td>{{ $it->item_name }}</td><td style="text-align:right;">₱{{ number_format($it->line_total,2) }}</td></tr>
@endforeach
<tr><td colspan="2" style="font-weight:700;">Total Proposed Budget</td><td style="text-align:right;font-weight:700;">₱{{ number_format($plan->items->sum('line_total'),2) }}</td></tr>
</tbody></table></div>
@else
<p class="summary-desc" style="margin-top:10px;">No budget breakdown items.</p>
@endif
@if($plan->supporting_document)<p><a href="{{ asset('storage/'.$plan->supporting_document) }}" target="_blank" class="btn btn-sm btn-secondary">View supporting document</a></p>@endif
</div>
<div class="dashboard-card"><h3>Financial Summary Panel</h3>
<p>Total Proposed: <strong>₱{{ number_format($plan->allocated_amount,2) }}</strong></p>
<p>Utilized: <strong>₱{{ number_format($plan->utilized_amount,2) }}</strong> ({{ $plan->utilization_rate }}%)</p>
<p>Remaining: <strong>₱{{ number_format($plan->remaining_amount,2) }}</strong></p>
<p>Available Funds (all active): <strong>₱{{ number_format($availableFunds ?? 0,2) }}</strong></p>
<p>Linked Allocations: <strong>{{ $plan->allocations->count() }}</strong> — ₱{{ number_format($plan->allocations->sum('amount'),2) }}</p>
<div class="fms-actions" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
@if(in_array($plan->status,['draft','for_revision','revision','rejected','cancelled']))
<a href="{{ route('accountant.budgets.edit',$plan) }}" class="btn btn-secondary">Edit / Revise</a>
<form method="POST" action="{{ route('accountant.budgets.submit',$plan) }}">@csrf<button class="btn btn-primary">Submit for Admin Approval</button></form>
<form method="POST" action="{{ route('accountant.budgets.duplicate',$plan) }}">@csrf<button class="btn btn-secondary">Duplicate</button></form>
@if($plan->status==='draft')
<form method="POST" action="{{ route('accountant.budgets.draft.destroy',$plan) }}" onsubmit="return confirm('Cancel this draft? History is preserved.');">@csrf @method('DELETE')<button class="btn btn-secondary">Cancel Draft</button></form>
@endif
@endif
@if(in_array($plan->status,['submitted','under_review']))<form method="POST" action="{{ route('accountant.budgets.cancel',$plan) }}">@csrf<button class="btn btn-secondary">Withdraw Submission</button></form>@endif
</div>
<p class="summary-desc" style="margin-top:8px;">Submitted plans lock editing. Accountant cannot approve own plans.</p>
</div>
</div>
@include('accountant.partials.approval-timeline',['history'=>$history ?? collect()])
@endsection
