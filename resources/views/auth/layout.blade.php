<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <main class="auth-main">
        <section class="auth-card" aria-labelledby="auth-heading">
            <header class="auth-header">
                <div class="auth-brand" aria-label="{{ config('app.name') }}">
                    <span class="auth-brand-mark" aria-hidden="true">{{ Str::upper(Str::substr(config('app.name'), 0, 1)) }}</span><span aria-hidden="true">{{ config('app.name') }}</span>
                </div>
                <h1 id="auth-heading">@yield('heading')</h1>
                <p>@yield('subtitle')</p>
            </header>
            <hr class="auth-divider">
            @yield('content')
        </section>
    </main>
    <footer class="auth-footer">© {{ now()->year }} {{ config('app.name') }}. All rights reserved.</footer>
</body>
</html>
