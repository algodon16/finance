<div class="dashboard-card" style="text-align:center;padding:32px;">
<h3>{{ $title ?? 'No records found' }}</h3>
<p class="page-subtitle">{{ $message ?? 'Try adjusting your filters or create a new record.' }}</p>
@if(!empty($actionUrl))<a href="{{ $actionUrl }}" class="btn btn-primary">{{ $actionLabel ?? 'Create' }}</a>@endif
</div>
