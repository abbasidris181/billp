@extends('portal.layout')
@section('title', $page->title ?? ('API '.strtoupper(substr($slug, 5)).' documentation'))
@section('content')
    <div class="portal-heading">
        <h1>@yield('title')</h1>
        <a class="portal-btn" href="{{ route(str_starts_with($slug, 'docs-') ? 'portal.api' : 'portal.pricing') }}">Back</a>
    </div>
    @if ($version)
        @include('portal.docs.index')
    @endif
    @if ($page)
        <section class="portal-panel portal-panel-body"><div class="portal-prose">{{ $page->body }}</div></section>

    @endif
@endsection
