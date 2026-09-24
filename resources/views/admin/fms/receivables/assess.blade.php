@extends('layouts.admin')
@section('title', 'Post Fee Assessment')
@section('content')
<div class="page-header">
    <h2>Post Fee Assessment</h2>
    <a class="btn btn-secondary" href="{{ route('admin.receivables.index') }}">Back to List</a>
</div>
<div class="fms-panel" style="max-width:640px;">
    <form method="POST" action="{{ route('admin.receivables.assess.store') }}">
        @csrf
        <div class="form-group"><label>Student <span class="required">*</span></label>
            <select name="student_id" class="form-control" required>
                <option value="">Select student</option>
                @foreach($students as $s)<option value="{{ $s->id }}">{{ $s->student_number }} - {{ $s->full_name }}</option>@endforeach
            </select></div>
        <div class="form-group"><label>Amount (PHP) <span class="required">*</span></label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
        <div class="form-group"><label>Description <span class="required">*</span></label><input type="text" name="description" class="form-control" placeholder="e.g. Tuition Fee - 2nd Semester" required></div>
        <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control"></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Post Assessment</button></div>
    </form>
</div>
@endsection
