<div class="plan-summary">
    <div>
        <span>Allowance</span>
        <p class="result-amount">₱{{ number_format($budgetPlan['allowance_amount'], 2) }}</p>
    </div>
    <div>
        <span>Frequency</span>
        <p class="result-text">{{ ucfirst($budgetPlan['allowance_frequency']) }}</p>
    </div>
</div>
<table class="table">
    <thead>
        <tr>
            <th>Category</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($budgetPlan['allocation'] as $key => $value)
            <tr>
                <td>{{ $categoryLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</td>
                <td>₱{{ number_format($value, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>Total</td>
            <td>₱{{ number_format($budgetPlan['total'], 2) }}</td>
        </tr>
    </tbody>
</table>
