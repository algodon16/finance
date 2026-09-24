@extends('layouts.app')

@section('title', 'Procurement Items Management')

@section('content')
<div class="page-header">
    <h2>Procurement Items Management</h2>
    <a href="{{ route('admin.procurementItems.create') }}" class="btn btn-primary">+ Add Item</a>
</div>

<div class="dashboard-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Stock / Sizes</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            @if($item->image_path)
                                <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->item_name }}" class="table-img">
                            @else
                                <span class="no-image">No Image</span>
                            @endif
                        </td>
                        <td>{{ $item->item_name }}</td>
                        <td>₱{{ number_format($item->price, 2) }}</td>
                        <td>
                            @if($item->requires_size)
                                @foreach($item->sizes as $sizeRow)
                                    {{ $sizeRow->size }}: {{ $sizeRow->quantity_available }}@if(!$loop->last), @endif
                                @endforeach
                                (Total: {{ $item->sizes->sum('quantity_available') }})
                            @else
                                {{ $item->stock_quantity }}
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $item->is_available ? 'green' : 'red' }}">
                                {{ $item->is_available ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="actions-cell">
                            <a href="{{ route('admin.procurementItems.edit', $item) }}" class="btn btn-sm btn-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.procurementItems.destroy', $item) }}" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No procurement items found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $items->withQueryString()->links() }}
    </div>
</div>
@endsection
