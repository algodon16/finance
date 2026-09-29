@if(!empty($history) && count($history))
<div class="dashboard-card" style="margin-top:16px;">
<h3>Approval Timeline &amp; History</h3>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
@foreach($history as $h)
<tr><td>{{ $h->created_at?->format('M d, Y h:i A') }}</td><td>{{ $h->user->name ?? 'System' }} ({{ $h->user->role ?? '—' }})</td><td><span class="badge badge-gray">{{ $h->action }}</span></td><td>{{ $h->description ?? ($h->module.' #'.$h->record_id) }}</td></tr>
@endforeach
</tbody></table></div>
<p class="summary-desc">Original audit trail is never deleted. Revisions increment the revision number.</p>
</div>
@endif
