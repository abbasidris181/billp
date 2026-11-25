@extends('auth.layout')

@section('title', 'Log in')
@section('heading', 'Welcome back')
@section('subtitle', 'Log in to your '.config('app.name').' account.')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" aria-labelledby="form-heading">
        @csrf
        <h2 id="form-heading" class="auth-form-heading">Log in with email or phone</h2>
        <div class="auth-fields">
            <x-auth.field name="identifier" label="Email address or phone number" autocomplete="username" maxlength="255" />
            <x-auth.field name="password" label="Password" type="password" autocomplete="current-password" />
        </div>
        <button class="auth-submit" type="submit">Log In</button>
    </form>
    <hr class="auth-divider auth-switch-divider">
    <a class="auth-switch" href="{{ route('register') }}">Don’t have an account? Sign up</a>
@endsection
