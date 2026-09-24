@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="dashboard">
    <div class="summary-cards">
        <div class="dashboard-card card-blue">
            <div class="card-content">
                <h3>Total Users</h3>
                <p class="card-amount">{{ number_format($totalUsers ?? 0) }}</p>
            </div>
        </div>

        <div class="dashboard-card card-green">
            <div class="card-content">
                <h3>Total Students</h3>
                <p class="card-amount">{{ number_format($totalStudents ?? 0) }}</p>
            </div>
        </div>

        <div class="dashboard-card card-yellow">
            <div class="card-content">
                <h3>Total Revenue</h3>
                <p class="card-amount">₱{{ number_format($totalRevenue ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="dashboard-card card-red">
            <div class="card-content">
                <h3>Pending Requests</h3>
                <p class="card-amount">{{ number_format($pendingRequests ?? 0) }}</p>
            </div>
        </div>
    </div>

    <div class="dashboard-row">
        <div class="dashboard-card">
            <h3>Recent Users</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers ?? [] as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge badge-{{ $user->role === 'admin' ? 'red' : ($user->role === 'accountant' ? 'blue' : ($user->role === 'cashier' ? 'yellow' : 'green')) }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                </td>
                                <td>{{ date('M d, Y', strtotime($user->created_at)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No users found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
