@extends('layouts.app')

@section('title', 'Budget Planner Result')

@section('content')
<div class="page-header">
    <h2>Budget Planner Recommendation</h2>
    <a href="{{ route('student.budget.index') }}" class="btn btn-secondary">Back to Budget Planner</a>
</div>

<div class="budget-results" style="display:block;">
    <h3>Your Budget Plan</h3>
    @if(isset($budgetPlan))
        @php
            $categoryLabels = [
                'school_expenses' => 'School Expenses',
                'daily_expenses' => 'Daily Expenses',
                'transportation' => 'Transportation',
                'savings' => 'Savings',
                'emergency_fund' => 'Emergency Fund',
            ];
        @endphp
        @include('student.budget-planner._plan', ['budgetPlan' => $budgetPlan, 'categoryLabels' => $categoryLabels])
    @else
        <p class="text-muted">No recommendation available. Please fill in your budget details first.</p>
    @endif
    <p class="plan-note">Budget recommendations are for planning purposes only and do not modify official school financial records.</p>
</div>
@endsection
