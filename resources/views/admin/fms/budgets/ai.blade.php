@extends('layouts.admin')
@section('title', 'AI-Assisted Budget Planning')
@section('content')
<div class="page-header">
    <h2>AI-Assisted Budget Planning</h2>
    <a class="btn btn-secondary" href="{{ route('admin.budgets.index') }}">Back to Budgets</a>
</div>

<div class="fms-panel" style="max-width:760px;">
    <h3>Payment Plan Inputs</h3>
    <form method="POST" action="{{ route('admin.budgets.ai.calculate') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Declared Allowance (PHP) *</label><input type="number" step="0.01" min="0" name="declared_allowance" class="form-control" value="{{ old('declared_allowance', $data['declared_allowance'] ?? '') }}" required></div>
            <div class="form-group"><label>Payment Frequency *</label>
                <select name="payment_frequency" class="form-control" required>
                    @foreach(['monthly','quarterly','semestral','annual','one-time'] as $f)
                        <option value="{{ $f }}" {{ old('payment_frequency', $data['payment_frequency'] ?? '') === $f ? 'selected' : '' }}>{{ ucfirst($f) }}</option>
                    @endforeach
                </select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Outstanding Balance (PHP) *</label><input type="number" step="0.01" min="0" name="outstanding_balance" class="form-control" value="{{ old('outstanding_balance', $data['outstanding_balance'] ?? '') }}" required></div>
            <div class="form-group"><label>Total Assessment (PHP)</label><input type="number" step="0.01" min="0" name="total_assessment" class="form-control" value="{{ old('total_assessment', $data['total_assessment'] ?? '') }}"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group"><label>Payment Deadline *</label><input type="date" name="payment_deadline" class="form-control" value="{{ old('payment_deadline', $data['payment_deadline'] ?? '') }}" required></div>
            <div class="form-group"><label>Available Budget (PHP)</label><input type="number" step="0.01" min="0" name="available_budget" class="form-control" value="{{ old('available_budget', $data['available_budget'] ?? '') }}"></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Generate Recommendation</button></div>
    </form>
</div>

@if(isset($recommendation))
<div class="ai-box">
    <span class="ai-tag">AI-Generated Recommendation</span>
    <h4>Suggested Payment Plan</h4>
    <table class="fms-table"><tbody>
        <tr><td style="width:260px;">Suggested installment amount</td><td><strong>P{{ number_format($recommendation['suggested_installment'], 2) }}</strong></td></tr>
        <tr><td>Number of installments</td><td>{{ $recommendation['num_installments'] }}</td></tr>
        <tr><td>Months to deadline</td><td>{{ $recommendation['months_to_deadline'] }}</td></tr>
        <tr><td>Projected remaining balance</td><td>P{{ number_format($recommendation['projected_remaining_after_plan'], 2) }}</td></tr>
        <tr><td>Payment completion projection</td><td>{{ $recommendation['completion_projection'] }}</td></tr>
        <tr><td>Budget allocation forecast</td><td>{{ $recommendation['allocation_forecast'] }}</td></tr>
        <tr><td>Institutional context</td><td>Average outstanding student balance P{{ number_format($context['avg_student_balance'], 2) }}; recent average collected payment P{{ number_format($context['recent_avg_payment'], 2) }}.</td></tr>
    </tbody></table>
    <h4 style="margin-top:14px;">Suggested Payment Schedule</h4>
    <table class="fms-table">
        <thead><tr><th>#</th><th>Due Date</th><th style="text-align:right;">Amount</th><th style="text-align:right;">Projected Remaining</th></tr></thead>
        <tbody>
        @foreach($recommendation['schedule'] as $s)
            <tr><td>{{ $s['installment'] }}</td><td>{{ $s['due'] }}</td><td style="text-align:right;">P{{ number_format($s['amount'], 2) }}</td><td style="text-align:right;">P{{ number_format($s['projected_remaining'], 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <p style="font-size:0.82rem;color:#475569;margin:12px 0 0;">This recommendation is advisory only and does not modify any financial record. The Admin must review and confirm before creating any budget plan or assessment.</p>
</div>
@endif
@endsection
