@extends('portal.layout')
@section('title', 'User Management')
@section('content')
    <div class="portal-heading">
        <div><h1>User Management</h1><p>Manage accounts, service pricing, and balances.</p></div>
        <div class="portal-actions">
            <a class="portal-btn" href="{{ route('admin.index') }}">Admin Dashboard</a>
            <a class="portal-btn portal-btn-primary" href="{{ route('admin.users.create') }}">Create user</a>
        </div>
    </div>
    <form class="portal-panel portal-panel-body portal-form" method="GET" action="{{ route('admin.users.index') }}">
        <label>Search by name or email<input name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Name or email"></label>
        <div class="portal-actions"><button class="portal-btn portal-btn-primary">Search</button><a class="portal-link" href="{{ route('admin.users.index') }}">Clear</a></div>
    </form>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>User directory</h2><span>{{ $users->total() }} accounts</span></header>
        <div class="portal-table-wrap"><table class="portal-table">
            <thead><tr><th>Name</th><th>Business name</th><th>Email</th><th>Email verification</th><th>Role</th><th>Account status</th><th>Balance</th><th>Manage</th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong></td>
                        <td>{{ $user->business_name ?? '—' }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->hasVerifiedEmail() ? 'Verified' : 'Unverified' }}</td>
                        <td>{{ \App\Models\User::ROLES[$user->role] }}</td>
                        <td><x-portal.status :status="$user->account_status" /></td>
                        <td><x-portal.amount :minor="$user->balance" /></td>
                        <td><a class="portal-link" href="{{ route('admin.users.edit', $user) }}">Manage<span class="sr-only"> {{ $user->name }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8">No accounts found.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($users->hasPages())<div class="portal-pagination">{{ $users->links('pagination::simple-tailwind') }}</div>@endif
    </section>
@endsection
