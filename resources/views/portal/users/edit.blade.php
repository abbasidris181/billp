@extends('portal.layout')
@section('title', $user->exists ? 'Manage user' : 'Create user')
@section('content')
    <div class="portal-heading">
        <div><h1>{{ $user->exists ? $user->name : 'Create user' }}</h1>
        @if ($user->exists)<p>{{ $user->email }} · {{ $user->hasVerifiedEmail() ? 'Email verified' : 'Email unverified' }}</p>@endif
        </div>
        <a class="portal-btn" href="{{ route('admin.users.index') }}">Back to users</a>
    </div>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Account details</h2>@if($user->exists)<x-portal.status :status="$user->account_status" />@endif</header>
        <form class="portal-panel-body portal-form" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if ($user->exists)@method('PUT')@endif
            <div class="portal-form-grid">
                <label>Full name<input name="name" value="{{ old('name', $user->name) }}" maxlength="255" required></label>
                <label>Email<input name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" required></label>
                <label>Business name<input name="business_name" value="{{ old('business_name', $user->business_name) }}" maxlength="255"></label>
                <label>Phone<input name="phone" type="tel" value="{{ old('phone', $user->phone) }}"></label>
                <label>Role<select name="role">
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <option value="{{ $key }}" @selected(old('role', $user->role ?? 'user') === $key)>{{ $label }}</option>
                    @endforeach
                </select></label>
                @if ($user->account_status === 'archived')
                    <label>Account status<input value="Archived" readonly></label>
                    <input type="hidden" name="account_status" value="active">
                @else
                    <label>Account status<select name="account_status">
                        @foreach (['active' => 'Active', 'suspended' => 'Suspended'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('account_status', $user->account_status ?? 'active') === $key)>{{ $label }}</option>
                        @endforeach
                    </select></label>
                @endif
                @unless ($user->exists)
                    <label>Password<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                    <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                @endunless
            </div>
            <div><button class="portal-btn portal-btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button></div>
        </form>
    </section>

    @if ($user->exists)
        <section class="portal-panel" id="individual-prices">
            <header class="portal-panel-head"><h2>Individual service prices</h2></header>
            <form class="portal-panel-body portal-form" method="POST" action="{{ route('admin.users.prices', $user) }}">
                @csrf
                @method('PUT')
                <p>Amounts are in naira. Leave an individual price blank to use the standard rate. Saving a rate here does not enable an unavailable service.</p>
                <div class="portal-table-wrap"><table class="portal-table">
                    <thead><tr><th>Service</th><th>API version</th><th>Standard price</th><th>Individual price (₦)</th></tr></thead>
                    <tbody>
                        @foreach ($prices as $price)
                            @php($field = $price->id ? 'prices['.$price->id.']' : 'new_prices['.$price->version.']['.$price->service.']')
                            @php($oldKey = $price->id ? 'prices.'.$price->id : 'new_prices.'.$price->version.'.'.$price->service)
                            <tr>
                                <td>{{ $price->label }}</td>
                                <td>{{ strtoupper($price->version) }}</td>
                                <td>@if ($price->price_minor === null) Not set @else <x-portal.amount :minor="$price->price_minor" /> @endif</td>
                                <td><input name="{{ $field }}" inputmode="decimal" pattern="[0-9]{1,8}(\.[0-9]{1,2})?"
                                    aria-label="{{ $price->label }} {{ strtoupper($price->version) }} individual price"
                                    value="{{ old($oldKey, $price->custom_price === null ? '' : number_format($price->custom_price / 100, 2, '.', '')) }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
                <div><button class="portal-btn portal-btn-primary">Save individual prices</button></div>
            </form>
        </section>
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Account actions</h2></header>
            <div class="portal-panel-body portal-actions">
                @if ($user->isActive() && ! $user->hasVerifiedEmail())
                    <form method="POST" action="{{ route('admin.users.verification', $user) }}">
                        @csrf
                        <button class="portal-btn">Resend verification</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="portal-actions">
                    @csrf
                    @if ($user->account_status === 'archived')
                        <button class="portal-btn" name="action" value="restore">Restore user</button>
                    @else
                        @if ($user->isActive())
                            <button class="portal-btn" name="action" value="suspend">Suspend user</button>
                        @else
                            <button class="portal-btn" name="action" value="reactivate">Reactivate user</button>
                        @endif
                        <button class="portal-btn" name="action" value="archive">Archive user</button>
                    @endif
                </form>
            </div>
        </section>
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Reset password</h2></header>
            <form class="portal-panel-body portal-form" method="POST" action="{{ route('admin.users.password', $user) }}">
                @csrf
                <div class="portal-form-grid">
                    <label>New password<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                    <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                </div>
                <div><button class="portal-btn portal-btn-primary">Reset password</button></div>
            </form>
        </section>
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Adjust balance</h2><span>Current balance <x-portal.amount :minor="$balance" /></span></header>
            <form class="portal-panel-body portal-form" method="POST" action="{{ route('admin.users.balance', $user) }}">
                @csrf
                <div class="portal-form-grid">
                    <label>Transaction type<select name="transaction_type"><option value="credit" @selected(old('transaction_type') === 'credit')>Credit</option><option value="debit" @selected(old('transaction_type') === 'debit')>Debit</option></select></label>
                    <label>Amount (₦)<input name="amount" inputmode="decimal" pattern="[0-9]{1,8}(\.[0-9]{1,2})?" value="{{ old('amount') }}" required></label>
                    <label>Reason<input name="reason" value="{{ old('reason') }}" maxlength="255" required></label>
                </div>
                <div><button class="portal-btn portal-btn-primary">Apply adjustment</button></div>
            </form>
        </section>
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Balance history</h2></header>
            <div class="portal-table-wrap"><table class="portal-table">
                <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Resulting balance</th><th>Reason</th><th>Acting super-admin ID</th></tr></thead>
                <tbody>
                    @forelse ($history as $entry)
                        <tr><td>{{ $entry->created_at }}</td><td>{{ $entry->amount_minor < 0 ? 'Debit' : 'Credit' }}</td>
                            <td><x-portal.amount :minor="$entry->amount_minor" /></td><td><x-portal.amount :minor="$entry->resulting_balance" /></td>
                            <td>{{ $entry->description }}</td><td>{{ $entry->actor_id ?? '—' }}</td></tr>
                    @empty
                        <tr><td colspan="6">No balance transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @if($history->hasPages())<div class="portal-pagination">{{ $history->links('pagination::simple-tailwind') }}</div>@endif
        </section>
    @endif
@endsection
