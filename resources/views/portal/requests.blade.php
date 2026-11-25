@extends('portal.layout')
@section('title', 'Request List')
@section('content')
    <div class="portal-heading">
        <div><h1>Request List</h1><p>Search verification history and inspect request results.</p></div>
        <a class="portal-btn" href="{{ route('portal.export', array_filter($filters)) }}">Export CSV</a>
    </div>
    <form class="portal-panel portal-form portal-filter" method="GET" action="{{ route('portal.requests') }}">
        <label class="portal-filter-search">Search<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference or service"></label>
        <label>Service<select name="service"><option value="">All services</option>@foreach($services as $service)<option @selected(($filters['service'] ?? '') === $service)>{{ $service }}</option>@endforeach</select></label>
        <label>Version<select name="version"><option value="">All versions</option>@foreach(['v1','v2','v3'] as $version)<option value="{{ $version }}" @selected(($filters['version'] ?? '') === $version)>{{ strtoupper($version) }}</option>@endforeach</select></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['pending','successful','failed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label>From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <div class="portal-actions"><button class="portal-btn portal-btn-primary">Apply filters</button><a href="{{ route('portal.requests') }}" class="portal-link">Clear</a></div>
    </form>
    <section class="portal-panel">
        <header class="portal-panel-head"><h2>Verification history</h2><span>{{ $requests->total() }} {{ Str::plural('request', $requests->total()) }}</span></header>
        <x-portal.request-table :records="$requests" />
        @if($requests->hasPages())<div class="portal-pagination">{{ $requests->links('pagination::simple-tailwind') }}</div>@endif
    </section>
@endsection
