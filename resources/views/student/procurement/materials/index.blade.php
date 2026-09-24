@extends('layouts.app')

@section('title', 'Learning Materials')

@section('content')
<div class="page-header">
    <div>
        <h2>Learning Materials</h2>
        <p class="page-subtitle">Browse and request available books and learning materials.</p>
    </div>
    <a href="{{ route('student.procurement.index') }}" class="btn btn-secondary">&larr; Back to Academic Items</a>
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

<div class="panel-card">
    <h3>Available Learning Materials</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Book Title</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($materials as $material)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $material->item_name }}</td>
                        <td>{{ $material->category ?? '-' }}</td>
                        <td>{{ $material->description ?? '-' }}</td>
                        <td>₱{{ number_format($material->price, 2) }}</td>
                        <td>Available: {{ $material->stock_quantity }} copies</td>
                        <td>
                            @if($material->stock_quantity > 0)
                                <span class="badge badge-green">In Stock</span>
                            @else
                                <span class="badge badge-gray">Out of Stock</span>
                            @endif
                        </td>
                        <td>
                            @if($material->stock_quantity > 0)
                                <button type="button" class="btn btn-sm btn-primary"
                                        onclick="openAddModal({{ $material->id }}, '{{ addslashes($material->item_name) }}', {{ $material->price }}, {{ $material->stock_quantity }})">Add</button>
                            @else
                                <button type="button" class="btn btn-sm btn-secondary" disabled>Out of Stock</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No learning materials available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel-card cart-card">
    <h3>My Requested Learning Materials</h3>
    @if(count($cartLines) > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Book Title</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cartLines as $line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $line['item']->item_name ?? 'N/A' }}</td>
                            <td>{{ $line['quantity'] }}</td>
                            <td>₱{{ number_format($line['item']->price ?? 0, 2) }}</td>
                            <td>₱{{ number_format($line['subtotal'], 2) }}</td>
                            <td><span class="badge badge-yellow">Pending</span></td>
                            <td>
                                <form method="POST" action="{{ route('student.procurement.materials.cart.remove') }}" class="inline-form">
                                    @csrf
                                    <input type="hidden" name="item_id" value="{{ $line['item']->id }}">
                                    <button type="submit" class="btn btn-sm btn-secondary">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="total-label"><strong>Total Amount</strong></td>
                        <td><strong>₱{{ number_format($cartTotal, 2) }}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <form method="POST" action="{{ route('student.procurement.materials.submit') }}">
            @csrf
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    @else
        <div class="empty-state">
            <p>No materials added yet. Click <strong>Add</strong> to include a book in your request.</p>
        </div>
    @endif
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Learning Material</h3>
            <button type="button" class="modal-close" aria-label="Close" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="POST" action="{{ route('student.procurement.materials.cart.add') }}" id="addModalForm">
            @csrf
            <input type="hidden" name="item_id" id="modalItemId">
            <div class="modal-body">
                <div class="modal-item">
                    <span>Book Title</span>
                    <p id="modalItemName" class="modal-value"></p>
                </div>
                <div class="modal-item">
                    <span>Unit Price</span>
                    <p id="modalItemPrice" class="modal-value"></p>
                </div>
                <div class="form-group">
                    <label for="modalQuantity">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" id="modalQuantity" class="form-control" value="1" min="1" required>
                    <small class="form-help" id="modalStockHelp"></small>
                </div>
                <div class="modal-item">
                    <span>Subtotal</span>
                    <p id="modalSubtotal" class="modal-value result-amount"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add to Request</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    var modalPrice = 0;
    var modalStock = 0;

    function peso(n) {
        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openAddModal(id, name, price, stock) {
        modalPrice = Number(price);
        modalStock = Number(stock);
        document.getElementById('modalItemId').value = id;
        document.getElementById('modalItemName').textContent = name;
        document.getElementById('modalItemPrice').textContent = peso(price);
        var qty = document.getElementById('modalQuantity');
        qty.value = 1;
        qty.max = stock;
        document.getElementById('modalStockHelp').textContent = 'Available: ' + stock + ' copies';
        updateSubtotal();
        document.getElementById('addModal').classList.add('active');
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
    }

    function updateSubtotal() {
        var qty = Math.max(1, parseInt(document.getElementById('modalQuantity').value, 10) || 1);
        if (qty > modalStock) {
            qty = modalStock;
            document.getElementById('modalQuantity').value = qty;
        }
        document.getElementById('modalSubtotal').textContent = peso(modalPrice * qty);
    }

    document.getElementById('modalQuantity').addEventListener('input', updateSubtotal);
    document.getElementById('addModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeAddModal();
        }
    });
</script>
<style>
    .page-subtitle { color: var(--text-muted); font-size: .9rem; margin-top: 4px; }
    .panel-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); padding: 24px; margin-bottom: 20px; }
    .panel-card h3 { margin-bottom: 16px; }
    .cart-card .inline-form { display: inline; }
    .total-label { text-align: right; }
    .modal-item { margin-bottom: 12px; }
    .modal-item label { font-size: .75rem; text-transform: uppercase; letter-spacing: .4px; color: var(--text-muted); font-weight: 600; }
    .modal-value { font-size: 1rem; font-weight: 500; }
    #addModal { display: none; }
    #addModal.active { display: flex; }
</style>
@endpush
@endsection
