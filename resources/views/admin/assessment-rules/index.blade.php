@extends('layouts.app')

@section('title', 'Fee Assessment')

@section('content')
<div class="page-header">
    <div>
        <h2>Fee Assessment</h2>
        <p class="page-subtitle">Manage and configure student fees and financial assessments. These fees will be included in the student's assessment and payment records.</p>
    </div>
    <a href="{{ route('admin.assessmentRules.create') }}" class="btn btn-primary">+ Add Assignment</a>
</div>

<div class="filter-tabs">
    <a href="{{ route('admin.feeAssessment.index') }}" class="tab">Fee Assessment</a>
    <a href="{{ route('admin.assessmentRules.index') }}" class="tab active">Fee Assignment</a>
</div>

<div class="dashboard-card">
    <h3>Fee Assignment</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Program</th>
                    <th>Year Level</th>
                    <th>Semester</th>
                    <th>Academic Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $index => $rule)
                    <tr>
                        <td>{{ $rules->firstItem() + $index }}</td>
                        <td>{{ $rule->program }}</td>
                        <td>{{ $rule->year_level }}</td>
                        <td>{{ $rule->semester }}</td>
                        <td>{{ $rule->academic_year }}</td>
                        <td class="actions-cell">
                            <a href="{{ route('admin.assessmentRules.edit', $rule) }}" class="btn btn-sm btn-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.assessmentRules.destroy', $rule) }}" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this fee assignment?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No fee assignments configured</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $rules->withQueryString()->links() }}
    </div>
</div>
@endsection
