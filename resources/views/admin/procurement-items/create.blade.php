@extends('layouts.app')

@section('title', 'Add Procurement Item')

@section('content')
<div class="page-header">
    <h2>Add Procurement Item</h2>
    <a href="{{ route('admin.procurementItems.index') }}" class="btn btn-secondary">Back to Items</a>
</div>

<div class="dashboard-card">
    <form method="POST" action="{{ route('admin.procurementItems.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="item_name">Item Name <span class="required">*</span></label>
            <input type="text" name="item_name" id="item_name" class="form-control @error('item_name') is-invalid @enderror" value="{{ old('item_name') }}" required autofocus>
            @error('item_name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description') }}</textarea>
            @error('description')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-row">
            <div class="form-group half">
                <label for="price">Price (₱) <span class="required">*</span></label>
                <input type="number" name="price" id="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price') }}" step="0.01" min="0" required>
                @error('price')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group half">
                <label for="stock_quantity">Stock Quantity <span class="required">*</span></label>
                <input type="number" name="stock_quantity" id="stock_quantity" class="form-control @error('stock_quantity') is-invalid @enderror" value="{{ old('stock_quantity') }}" min="0" required>
                @error('stock_quantity')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group half">
                <label for="image">Image</label>
                <input type="file" name="image" id="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                @error('image')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="requires_size" value="1" id="requires_size" {{ old('requires_size') ? 'checked' : '' }}>
                This item requires size selection (uniforms, clothing)
            </label>
            @error('requires_size')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div id="sizeSection" style="display: {{ old('requires_size') ? 'block' : 'none' }};">
            <div class="form-group">
                <span class="static-label">Size Inventory</span>
                <p class="form-help">Enter the available quantity for each size. Total stock is computed from these rows.</p>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Quantity Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $defaultSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL']; @endphp
                        @foreach($defaultSizes as $i => $sizeName)
                            <tr>
                                <td>
                                    <input type="text" name="sizes[{{ $i }}][size]" aria-label="Size name" class="form-control" value="{{ old('sizes.' . $i . '.size', $sizeName) }}">
                                </td>
                                <td>
                                    <input type="number" name="sizes[{{ $i }}][quantity]" aria-label="Quantity available for size" class="form-control" value="{{ old('sizes.' . $i . '.quantity', 0) }}" min="0">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_available" value="1" {{ old('is_available', '1') === '1' ? 'checked' : '' }}>
                Available for procurement
            </label>
            @error('is_available')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Item</button>
            <a href="{{ route('admin.procurementItems.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    (function () {
        var checkbox = document.getElementById('requires_size');
        var section = document.getElementById('sizeSection');
        if (!checkbox || !section) return;
        checkbox.addEventListener('change', function () {
            section.style.display = checkbox.checked ? 'block' : 'none';
        });
    })();
</script>
@endpush
@endsection
