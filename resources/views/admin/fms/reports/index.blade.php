@extends('layouts.admin')
@section('title', 'Financial Reporting and Compliance')
@section('content')
<div class="page-header"><h2>Financial Reporting and Compliance</h2></div>
<div class="fms-panel">
    <h3>Revenue Reports</h3>
    <p><a href="{{ route('admin.reports.revenue') }}">Daily / Monthly / Annual Revenue Report</a></p>
    <h3 style="margin-top:18px;">Expense Reports</h3>
    <p><a href="{{ route('admin.reports.expenses') }}">Expense Summary, Department Expense, and Disbursement Report</a></p>
    <h3 style="margin-top:18px;">Receivable Reports</h3>
    <p><a href="{{ route('admin.reports.receivables') }}">Outstanding Receivables, Overdue Accounts, and Student Ledger</a></p>
    <h3 style="margin-top:18px;">Payable Reports</h3>
    <p><a href="{{ route('admin.reports.payables') }}">Accounts Payable Aging, Vendor Payables, and Overdue Payables</a></p>
    <h3 style="margin-top:18px;">Budget Reports</h3>
    <p><a href="{{ route('admin.reports.budgets') }}">Budget vs Actual, Budget Utilization, and Fund Allocation</a></p>
    <h3 style="margin-top:18px;">Asset Reports</h3>
    <p><a href="{{ route('admin.reports.assets') }}">Asset Register, Depreciation Report, and Asset Disposal Report</a></p>
    <h3 style="margin-top:18px;">Executive Reports</h3>
    <p><a href="{{ route('admin.reports.executive') }}">Financial Position Summary, Cash Flow Summary, and Institutional Financial Overview</a></p>
</div>
@endsection
