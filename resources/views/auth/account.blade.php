@extends('auth.layout')

@section('title', 'Account')
@section('heading', 'Your account')
@section('subtitle', auth()->user()->name)

@section('content')
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="auth-submit" type="submit">Log Out</button>
    </form>
@endsection
