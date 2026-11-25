@extends('portal.layout')
@section('title', 'Instant-ipe batch report')
@section('content')
    <div class="portal-heading">
        <div><h1>Batch {{ $batch }}</h1><p>Instant-ipe report</p></div>
        <div class="portal-actions">
            @if ($canCheck)
                <form method="POST" action="{{ route('instant-ipe.batch.check', $batch) }}">
                    @csrf
                    <button class="portal-btn portal-btn-primary">Check batch status</button>
                </form>
            @endif
            <a class="portal-btn" href="{{ route('instant-ipe.index') }}">Back to Instant-ipe</a>
        </div>
    </div>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Batch results</h2><button class="portal-btn" type="button" data-copy-report="batch-report">Copy report</button></header>
        <div class="portal-panel-body portal-form">
            <p>Total {{ $counts->sum() }} · Successful {{ $counts->get('successful', 0) }} · Failed {{ $counts->get('failed', 0) }} · Pending {{ $counts->except(['successful', 'failed'])->sum() }}</p>
            <label>Tracking ID=Reply=NIN<textarea id="batch-report" rows="12" readonly>{{ $report }}</textarea></label>
            <span role="status" data-report-feedback></span>
        </div>
    </section>
@endsection
