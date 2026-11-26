@extends('auth.layout')

@section('title', 'Sign up')
@section('heading', 'Create your account')
@section('subtitle', 'Sign up to get started with '.config('app.name').'.')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" aria-labelledby="form-heading">
        @csrf
        <h2 class="auth-form-heading" id="form-heading">Sign up with email and phone</h2>
        <div class="auth-fields">
            <div class="auth-name-fields">
                <x-auth.field name="first_name" label="First name" autocomplete="given-name" maxlength="100" />
                <x-auth.field name="last_name" label="Last name" autocomplete="family-name" maxlength="100" />
            </div>
            <x-auth.field name="email" label="Email address" type="email" autocomplete="email" maxlength="255" />
            <x-auth.field name="phone" label="Phone number" type="tel" autocomplete="tel" />
            <div>
                <x-auth.field name="password" label="Password" type="password" autocomplete="new-password" minlength="8" />
                <div class="auth-strength" data-password-strength data-strength="empty">
                    <span class="auth-strength-bar" aria-hidden="true"></span>
                    <span class="auth-strength-label" aria-live="polite">Password strength</span>
                </div>
            </div>
            <x-auth.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="8" />
        </div>
        <button class="auth-submit" type="submit">Sign Up</button>
    </form>
    <hr class="auth-divider auth-switch-divider">
    <a class="auth-switch" href="{{ route('login') }}">Already have an account? Log in</a>
@endsection
