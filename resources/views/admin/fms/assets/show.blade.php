@extends('layouts.admin')
@section('title', 'Asset Details')
@section('content')
<div class="page-header">
    <h2>{{ $record->asset_name }} ({{ $record->asset_code }})</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.assets.edit', $record) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.assets.index') }}">Back to List</a>
    </div>
</div>

<div class="fms-stat-grid">
    <div class="fms-stat"><h4>Acquisition Cost</h4><p class="val">P{{ number_format($record->acquisition_cost, 2) }}</p></div>
    <div class="fms-stat"><h4>Accumulated Depreciation</h4><p class="val">P{{ number_format($record->accumulated_depreciation, 2) }}</p></div>
    <div class="fms-stat"><h4>Current Book Value</h4><p class="val">P{{ number_format($record->book_value, 2) }}</p></div>
    <div class="fms-stat"><h4>Annual / Monthly Depreciation</h4><p class="val" style="font-size:1.05rem;">P{{ number_format($record->annual_depreciation, 2) }} / P{{ number_format($record->monthly_depreciation, 2) }}</p></div>
</div>

<div class="fms-panel">
    <h3>Asset Information</h3>
    <table class="fms-table"><tbody>
        <tr><td style="width:220px;color:#64748b;">Category</td><td>{{ $record->asset_category }}</td></tr>
        <tr><td style="color:#64748b;">Serial number</td><td>{{ $record->serial_number ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Acquisition date</td><td>{{ $record->acquisition_date?->format('M d, Y') }}</td></tr>
        <tr><td style="color:#64748b;">Useful life</td><td>{{ $record->useful_life_years }} years (remaining {{ $record->remaining_life }} years)</td></tr>
        <tr><td style="color:#64748b;">Salvage value</td><td>P{{ number_format($record->salvage_value, 2) }}</td></tr>
        <tr><td style="color:#64748b;">Location / Department</td><td>{{ $record->location ?? '—' }} / {{ $record->department ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Custodian</td><td>{{ $record->custodian ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Status</td><td><span class="status">{{ ucwords(str_replace('_',' ',$record->asset_status)) }}</span></td></tr>
        <tr><td style="color:#64748b;">Depreciation method</td><td>Straight-Line: (Acquisition Cost - Salvage Value) / Useful Life</td></tr>
    </tbody></table>
    <form method="POST" action="{{ route('admin.assets.destroy', $record) }}" onsubmit="return confirm('Delete this asset?');" style="margin-top:12px;">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete Asset</button></form>
</div>
@endsection
