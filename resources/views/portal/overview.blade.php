@extends('portal.layout')
@section('title', 'Dashboard')
@section('content')
    <div class="portal-heading">
        <div><h1>Dashboard</h1><p>Track your balance and verification activity.</p></div>
        <a class="portal-btn portal-btn-primary" href="{{ route('portal.requests') }}">View requests <x-portal.icon name="arrow" /></a>
    </div>
    <div class="portal-metrics">
        <article class="portal-metric portal-balance"><span class="portal-metric-icon"><x-portal.icon name="wallet" /></span><p>Available balance</p><strong><x-portal.amount :minor="$balance" /></strong></article>
        <article class="portal-metric"><span class="portal-metric-icon"><x-portal.icon name="list" /></span><p>Total requests</p><strong>{{ number_format($counts->sum()) }}</strong></article>
        <article class="portal-metric"><span class="portal-metric-icon portal-metric-success"><x-portal.icon name="check" /></span><p>Successful</p><strong>{{ number_format($counts->get('successful', 0)) }}</strong></article>
        <article class="portal-metric"><span class="portal-metric-icon portal-metric-pending"><x-portal.icon name="clock" /></span><p>Pending</p><strong>{{ number_format($counts->get('pending', 0)) }}</strong></article>
        <article class="portal-metric"><span class="portal-metric-icon portal-metric-failure"><x-portal.icon name="fail" /></span><p>Failed</p><strong>{{ number_format($counts->get('failed', 0)) }}</strong></article>
    </div>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Recent verification requests</h2><a class="portal-link" href="{{ route('portal.requests') }}">View all <x-portal.icon name="arrow" /></a></header>
        <x-portal.request-table :records="$requests" empty="No verification requests yet." />
    </section>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Recent credits</h2><a class="portal-link" href="{{ route('portal.billing') }}">View billing <x-portal.icon name="arrow" /></a></header>
        <x-portal.ledger-table :records="$credits" empty="No credits yet." />
    </section>
@endsection
