<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal">
    <a href="#portal-main" class="portal-skip">Skip to content</a>
    <button type="button" class="portal-overlay" aria-label="Close navigation" data-nav-close hidden></button>
    <aside class="portal-sidebar" id="portal-navigation">
        <a class="portal-brand" href="{{ route('account') }}"><span aria-hidden="true">{{ Str::upper(Str::substr(config('app.name'), 0, 1)) }}</span> {{ config('app.name') }}</a>
        <nav aria-label="Main navigation">
            <p class="portal-nav-label">Workspace</p>
            <x-portal.nav route="account" icon="dashboard">Dashboard</x-portal.nav>
            <x-portal.nav route="portal.requests" match="portal.result" icon="list">Request List</x-portal.nav>
            <x-portal.nav route="portal.billing" icon="wallet">Billing</x-portal.nav>
            <x-portal.nav route="portal.pricing" icon="tag">Pricing</x-portal.nav>
            <x-portal.nav route="instant-ipe.index" match="instant-ipe.*" icon="shield">Instant-ipe</x-portal.nav>
            <x-portal.nav route="portal.api" icon="code">API Docs</x-portal.nav>
            @can('manage-portal')
                <p class="portal-nav-label">Administration</p>
                <x-portal.nav route="admin.index" match="admin.*" icon="settings">Admin Dashboard</x-portal.nav>
            @endcan
        </nav>
        <form class="portal-signout" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"><x-portal.icon name="logout" /> Sign out</button>
        </form>
    </aside>
    <div class="portal-workspace">
        <header class="portal-topbar">
            <div class="portal-topbar-start">
                <button type="button" class="portal-menu" aria-controls="portal-navigation" aria-expanded="false" aria-label="Open navigation" data-nav-toggle><x-portal.icon name="menu" /></button>
                <span>@yield('title')</span>
            </div>
            <div class="portal-profile">
                <span class="portal-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'super_admin' ? 'Super admin' : (auth()->user()->is_admin ? 'Administrator' : auth()->user()->email) }}</small></span>
            </div>
        </header>
        <main id="portal-main" class="portal-main">
            @if (session('notice'))
                <p class="portal-feedback" role="status">{{ session('notice') }}</p>
            @endif
            @if ($errors->any())
                <ul class="portal-feedback portal-feedback-error" role="alert">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
