@extends('portal.layout')
@section('title', 'API Docs')
@section('content')
    <div class="portal-heading"><div><h1>API Docs</h1><p>Manage your API key and find documentation by version.</p></div></div>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>API key</h2></header>
        <div class="portal-panel-body portal-form">
            @if($apiKey)
                <label>Current key<input class="portal-key" id="portal-api-key" value="{{ $apiKey }}" readonly spellcheck="false" autocomplete="off"></label>
                <p>Keep your API key private. Regenerating it replaces the current key.</p>
            @else
                <p>Generate an API key to get started.</p>
            @endif
            <div class="portal-actions">
                @if($apiKey)<button class="portal-btn" type="button" data-copy-key="portal-api-key">Copy key</button>@endif
                <form method="POST" action="{{ route('portal.key') }}" @if($apiKey) data-confirm="Replace your current API key?" @endif>
                    @csrf
                    <button class="portal-btn portal-btn-primary">{{ $apiKey ? 'Regenerate key' : 'Generate key' }}</button>
                </form>
                <span role="status" data-copy-feedback></span>
            </div>
        </div>
    </section>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Versioned documentation</h2></header>
        <div class="portal-panel-body portal-actions">
            @foreach(['v1','v2','v3'] as $version)
                <a class="portal-btn" href="{{ route('portal.page', 'docs-'.$version) }}">API {{ strtoupper($version) }} <x-portal.icon name="arrow" /></a>
            @endforeach
        </div>
    </section>
@endsection
