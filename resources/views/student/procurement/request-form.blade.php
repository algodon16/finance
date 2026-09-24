@extends('layouts.app')

@section('title', 'Request Item')

@section('content')
<div class="page-header">
    <h2>Request Item</h2>
    <a href="{{ route('student.procurement.show', $item->id) }}" class="btn btn-secondary">Back to Item</a>
</div>

<div class="dashboard-card">
    <div class="item-summary">
        <h3>{{ $item->item_name }}</h3>
        <p class="card-amount">₱{{ number_format($item->price, 2) }}</p>
        <p>Stock available: {{ $item->stock_quantity }}</p>
    </div>

    <form method="POST" action="{{ route('student.procurement.request') }}">
        @csrf
        <input type="hidden" name="item_id" value="{{ $item->id }}">

        <div class="form-group">
            <label for="quantity">Quantity <span class="required">*</span></label>
            <input type="number" name="quantity" id="quantity" class="form-control @error('quantity') is-invalid @enderror"
                   value="{{ old('quantity', 1) }}" min="1" max="{{ $item->stock_quantity }}" required>
            @error('quantity')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Any special instructions...">{{ old('remarks') }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a href="{{ route('student.procurement.show', $item->id) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
