@extends('auth.layout')

@section('title', 'Reset password')
@section('heading', 'Reset your password')
@section('subtitle', 'Choose a new password for your account.')

@section('content')
    @error('token')
        <p class="portal-feedback portal-feedback-error" role="alert">{{ $message }}</p>
    @enderror
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="auth-fields">
            <x-auth.field name="email" label="Email address" type="email" autocomplete="email" maxlength="255" :value="$email" />
            <x-auth.field name="password" label="New password" type="password" autocomplete="new-password" minlength="8" />
            <x-auth.field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" minlength="8" />
        </div>
        <button class="auth-submit" type="submit">Reset password</button>
    </form>
    <hr class="auth-divider auth-switch-divider">
    <a class="auth-switch" href="{{ route('password.request') }}">Request another reset link</a>
@endsection
