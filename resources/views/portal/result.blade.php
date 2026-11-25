@extends('portal.layout')
@section('title', 'Request details')
@section('content')
    <div class="portal-heading"><div><h1>Request details</h1><p>{{ $record->reference }}</p></div><a class="portal-btn" href="{{ $back ?? route('portal.requests') }}">Back to requests</a></div>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>{{ $record->service }}</h2><x-portal.status :status="$record->status" /></header>
        <div class="portal-panel-body"><p>{{ strtoupper($record->version) }} · {{ $record->created_at }}</p>
        <h3>Result</h3>
        @if ($record->result !== null)
            <pre class="portal-result">{{ json_encode(json_decode($record->result), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @else
            <p>No result is available for this request.</p>
        @endif
        </div>
    </section>
@endsection
