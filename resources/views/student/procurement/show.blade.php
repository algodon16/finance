@extends('layouts.app')

@section('title', 'Item Details')

@section('content')
<div class="page-header">
    <h2>Item Details</h2>
    <a href="{{ route('student.procurement.index') }}" class="btn btn-secondary">Back to Items</a>
</div>

@if($errors->any())
    <div class="validation-errors">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="item-detail-card">
    <div class="item-detail-image">
        @if($item->image_path)
            <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->item_name }}" class="item-large-image">
        @else
            <div class="item-placeholder-large">📦</div>
        @endif
    </div>

    <div class="item-detail-info">
        <h3>{{ $item->item_name }}</h3>
        <p class="item-detail-price">₱{{ number_format($item->price, 2) }}</p>

        <div class="item-detail-badges">
            @php
                $totalAvailable = $item->requires_size ? $item->sizes->sum('quantity_available') : $item->stock_quantity;
            @endphp
            @if($totalAvailable > 0)
                <span class="badge badge-green">In Stock</span>
            @else
                <span class="badge badge-gray">Out of Stock</span>
            @endif
        </div>

        @if($item->description)
            <div class="item-detail-description">
                <h4>Description</h4>
                <p>{{ $item->description }}</p>
            </div>
        @endif

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

        @if($totalAvailable > 0)
            <form method="POST" action="{{ route('student.procurement.request') }}" class="request-form" id="sizeRequestForm">
                @csrf
                @method('POST')
                <input type="hidden" name="item_id" value="{{ $item->id }}">

                @if($item->requires_size)
                    <div class="form-group">
                        <label for="size">Size <span class="required">*</span></label>
                        <select name="size" id="size" class="form-control @error('size') is-invalid @enderror" required>
                            <option value="">Select a size</option>
                            @foreach($item->sizes as $sizeRow)
                                @if($sizeRow->quantity_available > 0)
                                    <option value="{{ $sizeRow->size }}" data-available="{{ $sizeRow->quantity_available }}" {{ old('size') === $sizeRow->size ? 'selected' : '' }}>
                                        {{ $sizeRow->size }} - {{ $sizeRow->quantity_available }} available
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('size')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                @endif

                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" name="quantity" id="quantity" class="form-control @error('quantity') is-invalid @enderror"
                           value="{{ old('quantity', 1) }}" min="1" max="{{ $totalAvailable }}" required>
                    <small class="form-help" id="quantityHelp">Available: {{ $totalAvailable }}</small>
                    @error('quantity')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <span class="static-label">Subtotal</span>
                    <p class="result-amount" id="subtotalDisplay">₱{{ number_format($item->price, 2) }}</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Request Item</button>
                </div>
            </form>

            @push('scripts')
            <script>
                (function () {
                    var unitPrice = Number({{ $item->price }});
                    var sizeSelect = document.getElementById('size');
                    var qtyInput = document.getElementById('quantity');
                    var qtyHelp = document.getElementById('quantityHelp');
                    var subtotalDisplay = document.getElementById('subtotalDisplay');
                    var isSized = {{ $item->requires_size ? 'true' : 'false' }};

                    function peso(n) {
                        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    function currentMax() {
                        if (isSized && sizeSelect) {
                            var opt = sizeSelect.options[sizeSelect.selectedIndex];
                            var available = opt && opt.getAttribute('data-available') ? parseInt(opt.getAttribute('data-available'), 10) : 0;
                            return available;
                        }
                        return Number({{ $totalAvailable }});
                    }

                    function refresh() {
                        var max = currentMax();
                        qtyInput.max = max;
                        var qty = parseInt(qtyInput.value, 10) || 1;
                        if (qty < 1) {
                            qty = 1;
                            qtyInput.value = qty;
                        }
                        if (max > 0 && qty > max) {
                            qty = max;
                            qtyInput.value = qty;
                        }
                        qtyHelp.textContent = 'Available: ' + max;
                        subtotalDisplay.textContent = peso(unitPrice * qty);
                    }

                    if (sizeSelect) {
                        sizeSelect.addEventListener('change', refresh);
                    }
                    qtyInput.addEventListener('input', refresh);
                    refresh();
                })();
            </script>
            @endpush
        @else
            <div class="out-of-stock-notice">
                <p>This item is currently out of stock. Please check back later.</p>
            </div>
        @endif
    </div>
</div>
@endsection
