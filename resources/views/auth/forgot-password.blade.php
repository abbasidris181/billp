@extends('auth.layout')

@section('title', 'Forgot password')
@section('heading', 'Forgot password?')
@section('subtitle', 'Request a link to reset your password.')

@section('content')
    @if (session('status'))
        <p class="portal-feedback" role="status">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-auth.field name="email" label="Email address" type="email" autocomplete="email" maxlength="255" />
        <button class="auth-submit" type="submit">Send reset link</button>
    </form>
    <hr class="auth-divider auth-switch-divider">
    <a class="auth-switch" href="{{ route('login') }}">Back to login</a>
@endsection
