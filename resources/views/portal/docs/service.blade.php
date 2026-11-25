@extends('portal.layout')
@section('title', $name.' · API '.strtoupper($version))
@section('content')
    <div class="portal-heading">
        <div><h1>{{ $name }}</h1><p>API {{ strtoupper($version) }}</p></div>
        <a class="portal-btn" href="{{ route('portal.page', 'docs-'.$version) }}">Back to API {{ strtoupper($version) }} services</a>
    </div>

    @if ($version === 'v1' && $service === 'ipeclearing')
        @include('portal.docs.clearance')
    @elseif ($version === 'v1' && $service === 'personalization')
        @include('portal.docs.personalization')
    @elseif ($hasStatusEndpoint)
        @foreach (['Request submission', 'Check status'] as $operation)
            <section class="portal-panel">
                <header class="portal-panel-head"><h2>{{ $operation }}</h2><span>Contract pending</span></header>
                <div class="portal-panel-body">
                    <p>Endpoint path and HTTP method pending.</p>
                    <h3>Request headers</h3><p>Pending provider contract.</p>
                    <h3>Example request body</h3><p>Pending provider contract.</p>
                    <h3>Expected response</h3><p>Pending provider contract.</p>
                </div>
            </section>
        @endforeach
    @elseif (in_array($version, ['v1', 'v2'], true))
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Endpoint</h2></header>
            <div class="portal-panel-body"><x-portal.endpoint :route="'api.'.$version.'.'.$service" /></div>
        </section>
        @include('portal.docs.identity')
    @else
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Verification endpoint</h2><span>Contract pending</span></header>
            <div class="portal-panel-body">
                <p>Endpoint path and HTTP method pending.</p>
                <h3>Request headers</h3><p>Pending provider contract.</p>
                <h3>Example request body</h3><p>Pending provider contract.</p>
                <h3>Expected response</h3><p>Pending provider contract.</p>
            </div>
        </section>
    @endif
@endsection
