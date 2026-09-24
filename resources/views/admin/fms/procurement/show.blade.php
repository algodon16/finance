@extends('layouts.admin')
@section('title', 'Procurement Request Details')
@section('content')
<div class="page-header">
    <h2>Request {{ $record->request_number ?? ('#'.$record->id) }}</h2>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-secondary" href="{{ route('admin.procurement.edit', $record) }}">Edit</a>
        <a class="btn btn-secondary" href="{{ route('admin.procurement.index') }}">Back to List</a>
    </div>
</div>
<div class="fms-panel">
    <table class="fms-table"><tbody>
        <tr><td style="width:220px;color:#64748b;">Department</td><td>{{ $record->requesting_department ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Item / Service</td><td>{{ $record->item_description ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Quantity</td><td>{{ $record->quantity ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Estimated cost</td><td><strong>P{{ number_format($record->estimated_cost ?? $record->total_amount ?? 0, 2) }}</strong></td></tr>
        <tr><td style="color:#64748b;">Supplier</td><td>{{ $record->supplier ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Justification</td><td>{{ $record->justification ?? $record->remarks ?? '—' }}</td></tr>
        <tr><td style="color:#64748b;">Status</td><td><span class="status">{{ ucwords(str_replace('_',' ',$record->status)) }}</span></td></tr>
    </tbody></table>
    <div class="form-actions" style="flex-wrap:wrap;">
        @foreach(['submit'=>'Submit','review'=>'Under Review','approve'=>'Approve','reject'=>'Reject','order'=>'Mark Ordered','fulfill'=>'Mark Fulfilled','cancel'=>'Cancel'] as $a=>$label)
            <form method="POST" action="{{ route('admin.procurement.status', [$record, $a]) }}">@csrf<button class="btn {{ in_array($a, ['approve','fulfill']) ? 'btn-success' : (in_array($a, ['reject','cancel']) ? 'btn-danger' : (in_array($a, ['submit','order']) ? 'btn-primary' : 'btn-secondary')) }}" type="submit" onclick="return confirm('Set status to {{ $label }}?');">{{ $label }}</button></form>
        @endforeach
    </div>
</div>
@endsection
