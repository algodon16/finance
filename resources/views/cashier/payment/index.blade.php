@extends('layouts.app')

@section('title', 'Payment')

@section('content')
<div class="page-header">
    <div>
        <h2>Payment</h2>
        <p class="page-subtitle">Record a student payment.</p>
    </div>
</div>

<div class="payment-wrap">
    <div class="detail-card">
        <div class="detail-header">
            <h3>Payment Information</h3>
        </div>

        <form method="POST" action="{{ route('cashier.payment.store') }}" id="paymentForm">
            @csrf

            <div class="form-group">
                <label for="student_number">Student ID</label>
                <div class="search-row">
                    <input type="text" name="student_number" id="student_number" class="form-control"
                        value="{{ old('student_number') }}" placeholder="Enter Student ID" autocomplete="off">
                    <button type="button" class="btn btn-secondary" id="searchBtn">Search</button>
                </div>
                <span class="error-text" id="lookupError" style="display:none;"></span>
            </div>

            <input type="hidden" name="student_id" id="student_id" value="{{ old('student_id') }}">

            <div id="studentInfo" style="display:none;">
                <div class="form-group">
                    <label>Student Name</label>
                    <p class="static-value" id="infoName">—</p>
                </div>
                <div class="info-grid">
                    <div class="form-group">
                        <label>Program</label>
                        <p class="static-value" id="infoProgram">—</p>
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <p class="static-value" id="infoYear">—</p>
                    </div>
                </div>
                <div class="form-group">
                    <label>Current Balance</label>
                    <p class="static-value balance" id="infoBalance">₱0.00</p>
                </div>
            </div>

            <div class="form-group">
                <label for="payment_for">Payment For</label>
                <select name="payment_for" id="payment_for" class="form-control" disabled>
                    <option value="">Search for a student first</option>
                </select>
                @error('payment_for')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="amount">Amount</label>
                <div class="amount-row">
                    <span class="amount-prefix">₱</span>
                    <input type="number" name="amount" id="amount" class="form-control"
                        value="{{ old('amount') }}" min="0.01" step="0.01" placeholder="0.00">
                </div>
                <p class="hint-text" id="balanceHint" style="display:none;"></p>
                @error('amount')
                    <span class="error-text">{{ $message }}</span>
                @enderror
                @error('student_id')
                    <span class="error-text">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" id="recordBtn">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<div id="confirmModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Confirm Payment</h3>
            <button type="button" class="modal-close" aria-label="Close" id="confirmClose">&times;</button>
        </div>
        <div class="modal-body">
            <div class="confirm-row"><span>Student ID:</span><strong id="cStudentNumber">—</strong></div>
            <div class="confirm-row"><span>Student Name:</span><strong id="cStudentName">—</strong></div>
            <div class="confirm-row"><span>Payment For:</span><strong id="cPaymentFor">—</strong></div>
            <div class="confirm-row"><span>Amount:</span><strong id="cAmount">—</strong></div>
            <div class="confirm-row"><span>Current Balance:</span><strong id="cCurrent">—</strong></div>
            <div class="confirm-row total"><span>Remaining Balance:</span><strong id="cRemaining">—</strong></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="confirmCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmSubmit">Confirm Payment</button>
        </div>
    </div>
</div>

@push('scripts')
<style>
    .page-subtitle { margin: 4px 0 0; color: #666; font-size: 0.95rem; }
    .payment-wrap { max-width: 560px; margin: 0 auto; }
    .payment-wrap .detail-card { padding: 24px; }
    .search-row { display: flex; gap: 10px; }
    .search-row .form-control { flex: 1; }
    .static-value { margin: 4px 0 0; font-weight: 600; }
    .static-value.balance { color: #b45309; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .amount-row { display: flex; align-items: center; }
    .amount-prefix { padding: 8px 10px; background: #f1f5f9; border: 1px solid #cbd5e1; border-right: none; border-radius: 6px 0 0 6px; font-weight: 600; }
    .amount-row .form-control { border-radius: 0 6px 6px 0; }
    .hint-text { margin: 6px 0 0; font-size: 0.85rem; color: #475569; }
    .form-actions { display: flex; justify-content: flex-end; margin-top: 8px; }
    .error-text { color: #dc3545; font-size: 0.85rem; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .modal { background: #fff; border-radius: 8px; width: 90%; max-width: 460px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e0e0e0; }
    .modal-header h3 { margin: 0; }
    .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }
    .modal-body { padding: 20px; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 20px; border-top: 1px solid #e0e0e0; }
    .confirm-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e5e7eb; }
    .confirm-row span { color: #64748b; }
    .confirm-row.total strong { color: #15803d; }
</style>
<script>
(function () {
    var searchBtn = document.getElementById('searchBtn');
    var studentNumber = document.getElementById('student_number');
    var studentId = document.getElementById('student_id');
    var studentInfo = document.getElementById('studentInfo');
    var lookupError = document.getElementById('lookupError');
    var paymentFor = document.getElementById('payment_for');
    var amount = document.getElementById('amount');
    var balanceHint = document.getElementById('balanceHint');
    var recordBtn = document.getElementById('recordBtn');
    var modal = document.getElementById('confirmModal');
    var form = document.getElementById('paymentForm');

    var state = { studentNumber: '', fullName: '', balance: 0, fees: [] };
    var lookupUrl = "{{ route('cashier.payment.lookup') }}";

    function peso(n) {
        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ordinal(n) {
        n = parseInt(n, 10);
        if (isNaN(n)) return n;
        var s = ['th', 'st', 'nd', 'rd'], v = n % 100;
        return n + (s[(v - 20) % 10] || s[v] || s[0]) + ' Year';
    }

    function updateHint() {
        var val = parseFloat(amount.value);
        if (!isNaN(val) && val > 0 && state.balance > 0) {
            balanceHint.style.display = 'block';
            balanceHint.textContent = 'Current Balance: ' + peso(state.balance) +
                '  |  Remaining: ' + peso(Math.max(0, state.balance - val));
        } else if (state.balance > 0) {
            balanceHint.style.display = 'block';
            balanceHint.textContent = 'Current Balance: ' + peso(state.balance);
        } else {
            balanceHint.style.display = 'none';
        }
    }

    function doLookup() {
        var num = studentNumber.value.trim();
        if (!num) {
            lookupError.textContent = 'Please enter a Student ID.';
            lookupError.style.display = 'block';
            return;
        }
        lookupError.style.display = 'none';
        searchBtn.disabled = true;
        searchBtn.textContent = 'Searching...';

        fetch(lookupUrl + '?student_number=' + encodeURIComponent(num), { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
            .then(function (result) {
                if (!result.ok || !result.body.found) {
                    studentInfo.style.display = 'none';
                    studentId.value = '';
                    paymentFor.innerHTML = '<option value="">Search for a student first</option>';
                    paymentFor.disabled = true;
                    state = { studentNumber: '', fullName: '', balance: 0, fees: [] };
                    lookupError.textContent = (result.body && result.body.message) || 'No student found with that Student ID.';
                    lookupError.style.display = 'block';
                    updateHint();
                    return;
                }
                var s = result.body.student;
                state = { studentNumber: s.student_number, fullName: s.full_name, balance: parseFloat(result.body.outstanding_balance) || 0, fees: result.body.fees || [] };

                studentId.value = s.id;
                document.getElementById('infoName').textContent = s.full_name;
                document.getElementById('infoProgram').textContent = s.program || 'N/A';
                document.getElementById('infoYear').textContent = ordinal(s.year_level);
                document.getElementById('infoBalance').textContent = peso(state.balance);
                studentInfo.style.display = 'block';

                paymentFor.innerHTML = '';
                if (state.fees.length === 0) {
                    paymentFor.innerHTML = '<option value="">No applicable fees found</option>';
                    paymentFor.disabled = true;
                } else {
                    paymentFor.disabled = false;
                    var ph = document.createElement('option');
                    ph.value = '';
                    ph.textContent = 'Select a fee';
                    paymentFor.appendChild(ph);
                    state.fees.forEach(function (fee) {
                        var opt = document.createElement('option');
                        opt.value = fee.name;
                        opt.textContent = fee.name + ' — ' + peso(fee.amount);
                        paymentFor.appendChild(opt);
                    });
                }
                updateHint();
            })
            .catch(function () {
                lookupError.textContent = 'Lookup failed. Please try again.';
                lookupError.style.display = 'block';
            })
            .finally(function () {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Search';
            });
    }

    searchBtn.addEventListener('click', doLookup);
    studentNumber.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doLookup(); } });
    amount.addEventListener('input', updateHint);

    recordBtn.addEventListener('click', function () {
        if (!studentId.value) {
            lookupError.textContent = 'Please search for a valid Student ID first.';
            lookupError.style.display = 'block';
            return;
        }
        if (!paymentFor.value) {
            alert('Please select what the payment is for.');
            return;
        }
        var val = parseFloat(amount.value);
        if (isNaN(val) || val < 0.01) {
            alert('Please enter a valid amount greater than zero.');
            return;
        }
        if (val > state.balance) {
            alert('The amount cannot be greater than the outstanding balance of ' + peso(state.balance) + '.');
            return;
        }
        document.getElementById('cStudentNumber').textContent = state.studentNumber;
        document.getElementById('cStudentName').textContent = state.fullName;
        document.getElementById('cPaymentFor').textContent = paymentFor.value;
        document.getElementById('cAmount').textContent = peso(val);
        document.getElementById('cCurrent').textContent = peso(state.balance);
        document.getElementById('cRemaining').textContent = peso(Math.max(0, state.balance - val));
        modal.style.display = 'flex';
    });

    function closeModal() { modal.style.display = 'none'; }
    document.getElementById('confirmCancel').addEventListener('click', closeModal);
    document.getElementById('confirmClose').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.getElementById('confirmSubmit').addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Recording...';
        form.submit();
    });
})();
</script>
@endpush
@endsection
