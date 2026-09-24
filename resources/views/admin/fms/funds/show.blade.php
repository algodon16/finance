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
    <h3>Allocate to Budget / Department</h3>
    <form method="POST" action="{{ route('admin.funds.allocate', $fund) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        @csrf
        <div><label style="font-size:0.8rem;">Allocated To</label><br><input type="text" name="allocated_to" class="form-control" placeholder="Department / Program"></div>
        <div><label style="font-size:0.8rem;">Amount (PHP) *</label><br><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
        <div><label style="font-size:0.8rem;">Date *</label><br><input type="date" name="allocation_date" class="form-control" value="{{ today()->toDateString() }}" required></div>
        <div><button class="btn btn-primary" type="submit">Allocate</button></div>
    </form>
</div>

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
        <table class="fms-table"><thead><tr><th>Date</th><th>Allocated To</th><th style="text-align:right;">Amount</th></tr></thead><tbody>
        @forelse($fund->allocations as $a)<tr><td>{{ $a->allocation_date }}</td><td>{{ $a->allocated_to ?? ($a->budgetPlan->budget_name ?? '—') }}</td><td style="text-align:right;">P{{ number_format($a->amount, 2) }}</td></tr>
        @empty<tr><td colspan="3" style="color:#64748b;">No financial records available.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>
@endsection
