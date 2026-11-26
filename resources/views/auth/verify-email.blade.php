@extends('auth.layout')

@section('title', 'Verify your email')
@section('heading', 'Verify your email')
@section('subtitle', 'Open the verification link to continue to your dashboard.')

@section('content')
    <p>Check your inbox at <strong>{{ $email }}</strong>. If the email is missing, check your spam folder or request another link.</p>
    @if (session('verification_sent'))
        <p class="portal-feedback" role="status">A new verification link was sent.</p>
    @elseif (session('verification_delivery_failed'))
        <p class="portal-feedback portal-feedback-error" role="alert">We couldn’t send the verification email. Wait a moment and try again.</p>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="auth-submit">Resend verification email</button>
    </form>
    <hr class="auth-divider auth-switch-divider">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="auth-switch">Sign out</button>
    </form>
@endsection
