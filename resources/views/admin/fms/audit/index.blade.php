@extends('layouts.admin')
@section('title', 'Security and Audit Trail')
@section('content')
<div class="page-header"><h2>Security and Audit Trail</h2></div>
<div class="fms-panel no-print">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="max-width:240px;" placeholder="Search action, module, record" value="{{ request('search') }}">
        <select name="module" class="form-control" style="max-width:200px;"><option value="">All modules</option>@foreach($modules as $m)<option value="{{ $m }}" {{ request('module') === $m ? 'selected' : '' }}>{{ $m }}</option>@endforeach</select>
        <select name="action" class="form-control" style="max-width:180px;"><option value="">All actions</option>@foreach($actions as $a)<option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>@endforeach</select>
        <input type="date" name="date_from" class="form-control" style="max-width:170px;" value="{{ request('date_from') }}">
        <input type="date" name="date_to" class="form-control" style="max-width:170px;" value="{{ request('date_to') }}">
        <button class="btn btn-secondary" type="submit">Filter</button>
        <a class="btn btn-secondary" href="{{ route('admin.audit.index') }}">Reset</a>
    </form>
</div>
<div class="fms-panel">
    <div style="overflow-x:auto;">
    <table class="fms-table">
        <thead><tr><th>ID</th><th>Admin User</th><th>Action</th><th>Module</th><th>Record ID</th><th>Description</th><th>IP Address</th><th>Timestamp</th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            <tr>
                <td>{{ $l->id }}</td>
                <td>{{ $l->user->name ?? 'System' }}<br><small style="color:#64748b;">{{ $l->user->email ?? '' }}</small></td>
                <td><span class="status">{{ $l->action }}</span></td>
                <td>{{ $l->module }}</td>
                <td>{{ $l->record_id ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($l->description ?? '', 90) }}</td>
                <td>{{ $l->ip_address ?? '—' }}</td>
                <td>{{ $l->created_at?->format('Y-m-d H:i:s') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="color:#64748b;">No financial records available.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination-wrapper">{{ $logs->links() }}</div>
</div>
@endsection
