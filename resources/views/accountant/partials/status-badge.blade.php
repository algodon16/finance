@php
$s = strtolower($status ?? 'draft');
$label = \App\Services\WorkflowService::label($status ?? 'draft');
@endphp
@if(in_array($s, ['approved','active','completed','paid','reconciled','reviewed','fully paid']))
<span class="badge badge-green">{{ $label }}</span>
@elseif(in_array($s, ['rejected','overdue','variance','with_variance']))
<span class="badge badge-red">{{ $label }}</span>
@elseif(in_array($s, ['submitted','under_review','pending','pending approval','in_progress']))
<span class="badge badge-yellow">{{ $label }}</span>
@elseif(in_array($s, ['for_revision','revision']))
<span class="badge badge-red">{{ $label }}</span>
@elseif($s === 'cancelled')
<span class="badge badge-gray">{{ $label }}</span>
@else
<span class="badge badge-gray">{{ $label }}</span>
@endif
