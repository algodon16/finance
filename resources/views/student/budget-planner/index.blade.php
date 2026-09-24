@extends('layouts.app')

@section('title', 'AI Budget Planner')

@section('content')
@php
    $categoryLabels = [
        'school_expenses' => 'School Expenses',
        'daily_expenses' => 'Daily Expenses',
        'transportation' => 'Transportation',
        'savings' => 'Savings',
        'emergency_fund' => 'Emergency Fund',
    ];
@endphp

<div class="page-header">
    <div>
        <h2>AI Budget Planner</h2>
        <p class="page-subtitle">Plan your allowance with a simple recommended allocation.</p>
    </div>
</div>

@if($errors->any())
    <div class="validation-errors">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="planner-grid">
    <div class="form-card planner-form-card">
        <form id="budgetForm" method="POST" action="{{ route('student.budget.calculate') }}" novalidate>
            @csrf

            <div class="form-group">
                <label for="allowance_frequency">Allowance Frequency <span class="required">*</span></label>
                <select name="allowance_frequency" id="allowance_frequency" class="form-control" required>
                    <option value="">Select Frequency</option>
                    <option value="weekly" {{ old('allowance_frequency', $budget->allowance_frequency ?? '') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ old('allowance_frequency', $budget->allowance_frequency ?? '') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                </select>
                <span class="error-message" id="frequencyError" style="display:none;">Please select your allowance frequency.</span>
                @error('allowance_frequency')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="allowance_amount">Budget Amount <span class="required">*</span></label>
                <div class="amount-input">
                    <span class="amount-prefix">₱</span>
                    <input type="number" name="allowance_amount" id="allowance_amount" class="form-control"
                           value="{{ old('allowance_amount', $budget->allowance_amount ?? '') }}" step="0.01" min="0.01" required placeholder="Enter your budget amount">
                </div>
                <span class="error-message" id="amountError" style="display:none;"></span>
                @error('allowance_amount')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="generateBtn">Generate Budget Plan</button>
            </div>
        </form>
    </div>

    <div class="info-card">
        <h3>How It Works</h3>
        <ol class="info-list">
            <li>Select your allowance frequency.</li>
            <li>Enter your available budget.</li>
            <li>Click Generate Budget Plan.</li>
            <li>Review your recommended allocation.</li>
        </ol>
        <h4>Allocation Rates</h4>
        <ul class="rates-list">
            <li><span>School Expenses</span><strong>40%</strong></li>
            <li><span>Daily Expenses</span><strong>30%</strong></li>
            <li><span>Transportation</span><strong>15%</strong></li>
            <li><span>Savings</span><strong>10%</strong></li>
            <li><span>Emergency Fund</span><strong>5%</strong></li>
        </ul>
    </div>
</div>

<div class="budget-results" id="budgetResults" style="{{ isset($budgetPlan) ? 'display:block;' : 'display:none;' }}">
    <h3>Your Budget Plan</h3>
    <div id="budgetResultBody">
        @if(isset($budgetPlan))
            @include('student.budget-planner._plan', ['budgetPlan' => $budgetPlan, 'categoryLabels' => $categoryLabels])
        @endif
    </div>
    <p class="plan-note">Budget recommendations are for planning purposes only and do not modify official school financial records.</p>
</div>

<div class="ai-disclaimer">
    <p><strong>Note:</strong> Budget recommendations are for planning purposes only and do not modify official school financial records.</p>
</div>

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('budgetForm');
        var freq = document.getElementById('allowance_frequency');
        var amount = document.getElementById('allowance_amount');
        var freqError = document.getElementById('frequencyError');
        var amountError = document.getElementById('amountError');
        var results = document.getElementById('budgetResults');
        var body = document.getElementById('budgetResultBody');
        var labels = {
            school_expenses: 'School Expenses',
            daily_expenses: 'Daily Expenses',
            transportation: 'Transportation',
            savings: 'Savings',
            emergency_fund: 'Emergency Fund'
        };

        function peso(n) {
            return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function cap(s) {
            return s.charAt(0).toUpperCase() + s.slice(1);
        }

        function validate() {
            var ok = true;
            freqError.style.display = 'none';
            amountError.style.display = 'none';
            amountError.textContent = '';

            if (!freq.value) {
                freqError.style.display = 'block';
                ok = false;
            }

            var raw = (amount.value || '').trim();
            if (raw === '') {
                amountError.textContent = 'Please enter your budget amount.';
                amountError.style.display = 'block';
                ok = false;
            } else {
                var val = Number(raw);
                if (!isFinite(val)) {
                    amountError.textContent = 'Please enter a valid budget amount.';
                    amountError.style.display = 'block';
                    ok = false;
                } else if (val <= 0) {
                    amountError.textContent = 'Budget amount must be greater than zero.';
                    amountError.style.display = 'block';
                    ok = false;
                }
            }
            return ok;
        }

        function render(plan) {
            var html = '<div class="plan-summary">'
                + '<div><span>Allowance</span><p class="result-amount">' + peso(plan.allowance_amount) + '</p></div>'
                + '<div><span>Frequency</span><p class="result-text">' + cap(plan.allowance_frequency) + '</p></div>'
                + '</div>'
                + '<table class="table"><thead><tr><th>Category</th><th>Amount</th></tr></thead><tbody>';

            Object.keys(plan.allocation).forEach(function (key) {
                html += '<tr><td>' + (labels[key] || key) + '</td><td>' + peso(plan.allocation[key]) + '</td></tr>';
            });

            html += '<tr class="total-row"><td>Total</td><td>' + peso(plan.total) + '</td></tr>';
            html += '</tbody></table>';
            body.innerHTML = html;
            results.style.display = 'block';
            results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        form.addEventListener('submit', function (e) {
            if (!validate()) {
                e.preventDefault();
                return;
            }
            e.preventDefault();

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function (response) {
                if (response.status === 422) {
                    return response.json().then(function (data) {
                        var msgs = data.errors || {};
                        if (msgs.allowance_frequency) {
                            freqError.textContent = msgs.allowance_frequency[0];
                            freqError.style.display = 'block';
                        }
                        if (msgs.allowance_amount) {
                            amountError.textContent = msgs.allowance_amount[0];
                            amountError.style.display = 'block';
                        }
                    });
                }
                if (!response.ok) { throw new Error('request failed'); }
                return response.json().then(function (data) {
                    if (data.success) { render(data.budgetPlan); }
                });
            })
            .catch(function () {
                form.submit();
            });
        });
    })();
</script>
<style>
    .page-subtitle { color: var(--text-muted); font-size: .9rem; margin-top: 4px; }
    .planner-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; align-items: start; }
    .planner-form-card { max-width: none; }
    .amount-input { display: flex; align-items: stretch; }
    .amount-prefix { display: inline-flex; align-items: center; padding: 0 12px; background: #f7fafc; border: 1px solid var(--border); border-right: none; border-radius: var(--radius) 0 0 var(--radius); font-weight: 600; color: var(--text-muted); }
    .amount-input .form-control { border-radius: 0 var(--radius) var(--radius) 0; }
    .info-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); }
    .info-card h3 { margin-bottom: 12px; }
    .info-card h4 { margin: 16px 0 8px; font-size: .85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; }
    .info-list { list-style: decimal; padding-left: 20px; display: flex; flex-direction: column; gap: 8px; }
    .rates-list { display: flex; flex-direction: column; gap: 6px; }
    .rates-list li { display: flex; justify-content: space-between; font-size: .875rem; padding: 6px 0; border-bottom: 1px solid var(--border); }
    .rates-list li:last-child { border-bottom: none; }
    .plan-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .plan-summary label { font-size: .75rem; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); }
    .total-row td { font-weight: 700; }
    .plan-note { margin-top: 12px; font-size: .8125rem; color: var(--text-muted); }
    @media (max-width: 992px) { .planner-grid { grid-template-columns: 1fr; } }
</style>
@endpush
@endsection
