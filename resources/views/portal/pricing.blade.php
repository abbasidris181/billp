@extends('portal.layout')
@section('title', 'Pricing')
@section('content')
    <div class="portal-heading"><div><h1>Pricing</h1><p>Check service availability and per-request prices by API version.</p></div></div>
    @foreach (['v1', 'v2', 'v3'] as $version)
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>API {{ strtoupper($version) }}</h2><a class="portal-link" href="{{ route('portal.page', 'docs-'.$version) }}">View documentation <x-portal.icon name="arrow" /></a></header>
            @if ($prices->has($version))
                <div class="portal-table-wrap"><table class="portal-table">
                    <thead><tr><th>Service</th><th>Availability</th><th class="portal-align-right">Price per request</th></tr></thead>
                    <tbody>
                        @foreach($prices[$version] as $price)
                            <tr><td><strong>{{ $price->service }}</strong></td><td><x-portal.status :status="$price->available ? 'available' : 'unavailable'" /></td><td class="portal-align-right">@if($price->price_minor === null) Not set @else <x-portal.amount :minor="$price->price_minor" /> @endif</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
            @else
                <div class="portal-empty"><span><x-portal.icon name="tag" /></span><p>No service prices have been published for this version.</p></div>
            @endif
        </section>
    @endforeach
    @if ($pages->isNotEmpty())
        <section class="portal-panel">
            <header class="portal-panel-head"><h2>Service information and policies</h2></header>
            <div class="portal-panel-body portal-actions">
                @foreach ($pages as $page)<a class="portal-btn" href="{{ route('portal.page', $page->slug) }}">{{ $page->title }}</a>@endforeach
            </div>
        </section>
    @endif
@endsection
