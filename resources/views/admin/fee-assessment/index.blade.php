@extends('layouts.app')

@section('title', 'Fee Assessment')

@section('content')
<div class="page-header">
    <div>
        <h2>Fee Assessment</h2>
        <p class="page-subtitle">Manage and configure student fees and financial assessments. These fees will be included in the student's assessment and payment records.</p>
    </div>
    <a href="{{ route('admin.feeAssessment.create') }}" class="btn btn-primary">+ Add Fee</a>
</div>

<div class="filter-tabs">
    <a href="{{ route('admin.feeAssessment.index') }}" class="tab active">Fee Assessment</a>
    <a href="{{ route('admin.assessmentRules.index') }}" class="tab">Fee Assignment</a>
</div>

<div class="alert alert-info">The fees listed here will be used in computing the student's total assessment.</div>

<div class="dashboard-card">
    <h3>Fee Assessment</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fee Name</th>
                    <th>Description</th>
                    <th>Default Amount (₱)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fees as $index => $fee)
                    <tr>
                        <td>{{ $fees->firstItem() + $index }}</td>
                        <td>{{ $fee->fee_name }}</td>
                        <td>{{ $fee->description ?? '—' }}</td>
                        <td>₱{{ number_format($fee->default_amount, 2) }}</td>
                        <td class="actions-cell">
                            <a href="{{ route('admin.feeAssessment.edit', $fee) }}" class="btn btn-sm btn-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.feeAssessment.destroy', $fee) }}" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this fee?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No fees configured</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $fees->withQueryString()->links() }}
    </div>
</div>
@endsection
