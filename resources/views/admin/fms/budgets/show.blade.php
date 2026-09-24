@extends('layouts.admin')
@section('title', 'Budget Plan Details')
@section('content')
<div class="page-header">
    <h2>{{ $budget->budget_name }}</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.budgets.edit', $budget) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.budgets.index') }}">Back to List</a>
    </div>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Allocated</h4><p class="val">P{{ number_format($budget->allocated_amount, 2) }}</p></div>
    <div class="fms-stat"><h4>Utilized (Allocated)</h4><p class="val">P{{ number_format($budget->utilized_amount, 2) }}</p></div>
    <div class="fms-stat"><h4>Remaining</h4><p class="val">P{{ number_format($budget->remaining_amount, 2) }}</p></div>
    <div class="fms-stat"><h4>Utilization Rate</h4><p class="val">{{ $budget->utilization_rate }}%</p></div>
</div>

<div class="fms-panel">
    <h3>Plan Information</h3>
    <table class="fms-table"><tbody>
        <tr><td style="width:220px;color:#64748b;">Fiscal Year</td><td>{{ $budget->fiscal_year }}</td></tr>
        <tr><td style="color:#64748b;">Department</td><td>{{ $budget->department ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Category</td><td>{{ $budget->budget_category }}</td></tr>
        <tr><td style="color:#64748b;">Period</td><td>{{ $budget->start_date?->format('M d, Y') }} to {{ $budget->end_date?->format('M d, Y') }}</td></tr>
        <tr><td style="color:#64748b;">Status</td><td><span class="status">{{ ucfirst($budget->status) }}</span></td></tr>
        <tr><td style="color:#64748b;">Description</td><td>{{ $budget->description ?? '—' }}</td></tr>
    </tbody></table>
</div>

<div class="fms-panel">
    <h3>Allocate Funds</h3>
    <form method="POST" action="{{ route('admin.budgets.allocate', $budget) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        @csrf
        <div><label style="font-size:0.8rem;">Type *</label><br>
            <select name="allocation_type" class="form-control" required>
                <option value="department">Department allocation</option>
                <option value="program">Program allocation</option>
                <option value="scholarship">Scholarship allocation</option>
                <option value="operational">Operational allocation</option>
                <option value="academic">Academic allocation</option>
            </select></div>
        <div><label style="font-size:0.8rem;">Allocated To</label><br><input type="text" name="allocated_to" class="form-control" placeholder="Department / Program"></div>
        <div><label style="font-size:0.8rem;">Amount (PHP) *</label><br><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
        <div><label style="font-size:0.8rem;">Date *</label><br><input type="date" name="allocation_date" class="form-control" value="{{ today()->toDateString() }}" required></div>
        <div><label style="font-size:0.8rem;">Remarks</label><br><input type="text" name="remarks" class="form-control"></div>
        <div><button class="btn btn-primary" type="submit">Allocate</button></div>
    </form>
</div>

<div class="fms-panel">
    <h3>Allocation History</h3>
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>Date</th><th>Type</th><th>Allocated To</th><th style="text-align:right;">Amount</th><th>Remarks</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($budget->allocations as $a)
            <tr>
                <td>{{ $a->allocation_date }}</td>
                <td>{{ ucfirst($a->allocation_type) }}</td>
                <td>{{ $a->allocated_to ?? '—' }}</td>
                <td style="text-align:right;">P{{ number_format($a->amount, 2) }}</td>
                <td>{{ $a->remarks ?? '—' }}</td>
                <td><form method="POST" action="{{ route('admin.budgets.allocations.destroy', [$budget, $a]) }}" onsubmit="return confirm('Remove this allocation?');">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit">Remove</button></form></td>
            </tr>
        @empty
            <tr><td colspan="6" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <form method="POST" action="{{ route('admin.budgets.destroy', $budget) }}" onsubmit="return confirm('Delete this budget plan? This cannot be undone.');" style="margin-top:14px;">
        @csrf @method('DELETE')
        <button class="btn btn-danger" type="submit">Delete Budget Plan</button>
    </form>
</div>
@endsection
