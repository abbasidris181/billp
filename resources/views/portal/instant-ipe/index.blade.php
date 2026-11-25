@extends('portal.layout')
@section('title', 'Instant-ipe')
@section('content')
    <div class="portal-heading">
        <div><h1>Instant-ipe</h1><p>Submit tracking IDs for IPE clearing and check their status here.</p></div>
        <span>Wallet balance <strong><x-portal.amount :minor="$balance" /></strong></span>
    </div>

    @if ($receipt = session('instant_ipe_receipt'))
        <p class="portal-feedback" role="status">
            {{ $receipt['count'] }} {{ Str::plural('request', $receipt['count']) }} submitted.
            Total wallet charge <strong><x-portal.amount :minor="$receipt['total']" /></strong>.
            @if ($receipt['batch_id'])
                <a class="portal-link" href="{{ route('instant-ipe.batch', $receipt['batch_id']) }}">Batch {{ $receipt['batch_id'] }}</a>
            @endif
        </p>
    @endif

    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Submit tracking IDs</h2></header>
        <form class="portal-panel-body portal-form" method="POST" action="{{ route('instant-ipe.store') }}" data-instant-ipe-form>
            @csrf
            <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Str::uuid()) }}">
            @php($bulk = old('mode') === 'bulk')
            <fieldset class="portal-ipe-modes">
                <legend>Submission mode</legend>
                <label><input type="radio" name="mode" value="single" @checked(! $bulk)> Single tracking ID</label>
                <label><input type="radio" name="mode" value="bulk" @checked($bulk)> Bulk input</label>
            </fieldset>
            <div class="portal-ipe-prices">
                <p>Price per tracking ID @if($unitPrice === null) Unavailable @else <strong><x-portal.amount :minor="$unitPrice" /></strong> @endif</p>
                <p>Bulk total equals the per-ID price multiplied by the number of tracking IDs.</p>
            </div>
            <div data-ipe-mode="single" @if($bulk) hidden @endif>
                <label>Tracking ID<input type="text" name="tracking_ids" value="{{ $bulk ? '' : old('tracking_ids') }}" minlength="15" maxlength="15" pattern="[A-Za-z0-9]{15}" autocomplete="off" spellcheck="false" required @disabled($bulk)></label>
            </div>
            <div data-ipe-mode="bulk" @unless($bulk) hidden @endunless>
                <label>Tracking IDs<textarea name="tracking_ids" rows="6" autocomplete="off" spellcheck="false" aria-describedby="bulk-tracking-help" required @disabled(! $bulk)>{{ $bulk ? old('tracking_ids') : '' }}</textarea></label>
                <p id="bulk-tracking-help">Enter 15-character IDs separated by lines, spaces, commas, or semicolons. Joined IDs are split into groups of 15.</p>
            </div>
            <div><button class="portal-btn portal-btn-primary" type="submit">Submit</button></div>
        </form>
    </section>

    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Request history</h2><span>Refreshes every 15 seconds</span></header>
        <div data-ipe-history="{{ route('instant-ipe.history') }}">
            @include('portal.instant-ipe.history')
        </div>
    </section>
@endsection
