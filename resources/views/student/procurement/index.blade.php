@extends('layouts.app')

@section('title', 'Academic Items')

@section('content')
<div class="page-header">
    <h2>Academic Items</h2>
</div>

<div class="items-grid" id="academic-items">
    @forelse($items ?? [] as $item)
        <div class="item-card">
            <div class="item-image">
                @if($item->image_path)
                    <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->item_name }}">
                @else
                    <div class="item-placeholder">📦</div>
                @endif
            </div>
            <div class="item-info">
                <h3 class="item-name">{{ $item->item_name }}</h3>
                <p class="item-price">₱{{ number_format($item->price, 2) }}</p>
                <div class="item-badges">
                    @if($item->requires_size)
                        @if($item->sizes->sum('quantity_available') > 0)
                            <span class="badge badge-green">In Stock</span>
                        @else
                            <span class="badge badge-gray">Out of Stock</span>
                        @endif
                    @elseif($item->stock_quantity > 0)
                        <span class="badge badge-green">In Stock</span>
                    @else
                        <span class="badge badge-gray">Out of Stock</span>
                    @endif
                </div>
                @if($item->requires_size)
                    <div class="size-availability">
                        <p class="size-availability-title">Available Sizes:</p>
                        <ul>
                            @forelse($item->sizes as $sizeRow)
                                <li class="{{ $sizeRow->quantity_available > 0 ? '' : 'out' }}">{{ $sizeRow->size }} - {{ $sizeRow->quantity_available }} available</li>
                            @empty
                                <li class="out">No sizes configured</li>
                            @endforelse
                        </ul>
                    </div>
                @else
                    <p class="stock-line">Available: {{ $item->stock_quantity }} {{ $item->isLearningMaterial() ? 'copies' : 'pcs' }}</p>
                @endif
                @if(isset($materialsBundleId) && $item->id === $materialsBundleId)
                    <a href="{{ route('student.procurement.materials.index') }}" class="btn btn-primary btn-block">Browse Materials</a>
                @else
                    <a href="{{ route('student.procurement.show', $item->id) }}" class="btn btn-primary btn-block">Request</a>
                @endif
            </div>
        </div>
    @empty
        <div class="empty-state">
            <p>No academic items available at this time.</p>
        </div>
    @endforelse
</div>

<div class="recent-requests">
    <div class="recent-requests-head">
        <div>
            <h3>My Recent Requests</h3>
            <p class="section-subtitle">View the academic items you have recently requested.</p>
        </div>
    </div>

    @if(($recentRequests ?? collect())->isNotEmpty())
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentRequests as $recent)
                        <tr>
                            <td>REQ-{{ str_pad($recent->id, 4, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                @foreach($recent->items as $requestItem)
                                    {{ $requestItem->item->item_name ?? 'N/A' }}@if(!empty($requestItem->size)) (Size: {{ $requestItem->size }})@endif × {{ $requestItem->quantity ?? 'N/A' }}@if(!$loop->last)<br>@endif
                                @endforeach
                            </td>
                            <td>{{ $recent->items->sum('quantity') }}</td>
                            <td>₱{{ number_format($recent->total_amount, 2) }}</td>
                            <td>{{ \Carbon\Carbon::parse($recent->created_at)->format('M d, Y') }}</td>
                            <td>
                                @php $rStatus = $recent->status; @endphp
                                @if(in_array($rStatus, ['submitted', 'pending_approval']))
                                    <span class="badge badge-yellow">{{ ucfirst(str_replace('_', ' ', $rStatus)) }}</span>
                                @elseif($rStatus === 'approved')
                                    <span class="badge badge-green">Approved</span>
                                @elseif($rStatus === 'rejected')
                                    <span class="badge badge-red">Rejected</span>
                                @elseif(in_array($rStatus, ['processing', 'for_payment', 'ready_for_pickup']))
                                    <span class="badge badge-blue">{{ ucfirst(str_replace('_', ' ', $rStatus)) }}</span>
                                @else
                                    <span class="badge badge-blue">{{ ucfirst(str_replace('_', ' ', $rStatus)) }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('student.procurement.request.show', $recent->id) }}" class="btn btn-sm btn-secondary">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">
            <p><strong>No requests yet.</strong></p>
            <p>You haven't requested any academic items yet.</p>
            <a href="#academic-items" class="btn btn-primary">Browse Academic Items</a>
        </div>
    @endif
</div>

@endsection
