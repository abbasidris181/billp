@extends('portal.layout')
@section('title', 'Billing')
@section('content')
    <div class="portal-heading"><div><h1>Billing</h1><p>See your balance and account transactions.</p></div></div>
    <div class="portal-metrics"><article class="portal-metric portal-balance"><span class="portal-metric-icon"><x-portal.icon name="wallet" /></span><p>Current balance</p><strong><x-portal.amount :minor="$balance" /></strong></article></div>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Credit and debit history</h2></header>
        <x-portal.ledger-table :records="$entries" empty="No transactions yet." />
        @if($entries->hasPages())<div class="portal-pagination">{{ $entries->links('pagination::simple-tailwind') }}</div>@endif
    </section>
@endsection
