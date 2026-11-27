@extends('portal.layout')
@section('title', 'Admin Dashboard')
@section('content')
    @php($section ??= 'overview')
    <div class="portal-heading"><div><h1>Admin Dashboard</h1><p>Monitor platform activity and manage services, applications, and published content.</p></div></div>
    <nav class="portal-tabs" aria-label="Admin navigation">
        @foreach ([
            'overview' => ['Overview', route('admin.index')],
            ...(auth()->user()->can('manage-users') ? ['users' => ['Users', route('admin.users.index')]] : []),
            'requests' => ['Requests', route('admin.records', 'requests')],
            'applications' => ['Applications', route('admin.records', 'applications')],
            'pricing' => ['Pricing', route('admin.catalog')],
            'error-charge' => ['Error Charge', route('admin.error-charge')],
            'services' => ['Service pages', route('admin.content', 'services')],
            'docs' => ['Documentation', route('admin.content', 'docs')],
            'policies' => ['Policies', route('admin.content', 'policies')],
        ] as $tab => [$label, $url])
            <a href="{{ $url }}" @class(['portal-btn', 'portal-tab-active' => $section === $tab]) @if($section === $tab) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @switch($section)
        @case('overview')
            <div class="portal-metrics">
                @foreach ($counts as $label => $value)<article class="portal-metric"><span class="portal-metric-icon"><x-portal.icon name="dashboard" /></span><p>{{ $label }}</p><strong>{{ number_format($value) }}</strong></article>@endforeach
            </div>
            @break
        @case('requests')
            <section class="portal-panel">
                <header class="portal-panel-head"><h2>Verification requests</h2></header>
                <x-portal.request-table :records="$records" :admin="true" />
                @if($records->hasPages())<div class="portal-pagination">{{ $records->links('pagination::simple-tailwind') }}</div>@endif
            </section>
            @break
        @case('applications')
            <section class="portal-panel">
                <header class="portal-panel-head"><h2>Live access applications</h2></header>
                @forelse($records as $record)
                    <article class="portal-panel-body portal-review">
                        <div><strong>{{ $record->business_name }}</strong><p>{{ $record->email }} · {{ $record->registration_number }}</p></div>
                        <x-portal.status :status="$record->status" />
                        <p class="portal-prose">{{ $record->use_case }}</p>
                        <div class="portal-actions">
                            <a class="portal-btn" href="{{ route('admin.document', $record->id) }}">Download document</a>
                            @if ($record->status === 'pending')
                                <form class="portal-actions" method="POST" action="{{ route('admin.review', $record->id) }}">
                                    @csrf
                                    <button class="portal-btn portal-btn-primary" name="status" value="approved">Approve</button>
                                    <button class="portal-btn" name="status" value="rejected">Reject</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="portal-empty"><span><x-portal.icon name="shield" /></span><p>No live access applications yet.</p></div>
                @endforelse
                @if($records->hasPages())<div class="portal-pagination">{{ $records->links('pagination::simple-tailwind') }}</div>@endif
            </section>
            @break
        @case('error-charge')
            <section class="portal-panel portal-form-width">
                <header class="portal-panel-head"><h2>Error Charge</h2><span>API v1</span></header>
                <form class="portal-panel-body portal-form" method="POST" action="{{ route('admin.error-charge.save') }}">
                    @csrf
                    <input type="hidden" name="charge_errors" value="0">
                    <label class="portal-toggle">
                        <input type="checkbox" role="switch" name="charge_errors" value="1" @checked($chargeErrors) aria-describedby="error-charge-description">
                        Charge for provider timeouts and unsuccessful verifications
                    </label>
                    <p id="error-charge-description">Uses the service’s v1 error charge from Pricing. If an account has insufficient funds, the request is declined without a debit.</p>
                    <div><button class="portal-btn portal-btn-primary">Save setting</button></div>
                </form>
            </section>
            @break
        @case('pricing')
            <section class="portal-panel">
                <header class="portal-panel-head"><h2>Service pricing</h2></header>
                <div class="portal-table-wrap"><table class="portal-table">
                    <thead><tr><th>Service</th><th>API version</th><th>Price per request</th><th>Error charge</th><th>Availability</th><th>Manage</th></tr></thead>
                    <tbody>
                        @foreach ($prices as $price)
                            <tr>
                                <td><strong>{{ $price->label }}</strong></td>
                                <td>{{ strtoupper($price->version) }}</td>
                                <td>@if($price->price_minor === null)Not set @else<x-portal.amount :minor="$price->price_minor" />@endif</td>
                                <td>@if(!$price->error_charge_minor)None @else<x-portal.amount :minor="$price->error_charge_minor" />@endif</td>
                                <td><x-portal.status :status="$price->available ? 'available' : 'unavailable'" /></td>
                                <td>
                                    <details>
                                        <summary class="portal-link">{{ $price->price_minor === null ? 'Set price' : 'Edit price' }}</summary>
                                        <x-portal.price-form :price="$price" />
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
                @if($prices->hasPages())<div class="portal-pagination">{{ $prices->links('pagination::simple-tailwind') }}</div>@endif
            </section>
            <details class="portal-panel portal-edit">
                <summary><strong>Add another service price</strong><span>Amounts in naira</span></summary>
                <x-portal.price-form />
            </details>
            @break
        @default
            <section class="portal-panel">
                <header class="portal-panel-head"><h2>Publish {{ $section === 'docs' ? 'API documentation' : ($section === 'services' ? 'a service page' : 'a policy') }}</h2></header>
                <x-portal.page-form :category="$section" />
            </section>
            <section class="portal-panel">
                <header class="portal-panel-head"><h2>Published pages</h2></header>
                @forelse($pages as $page)
                    <details class="portal-edit">
                        <summary><strong>{{ $page->title }}</strong><a class="portal-link" href="{{ route('portal.page', $page->slug) }}">View page</a></summary>
                        <x-portal.page-form :category="$section" :page="$page" />
                    </details>
                @empty
                    <div class="portal-empty"><span><x-portal.icon name="list" /></span><p>No published pages yet.</p></div>
                @endforelse
                @if($pages->hasPages())<div class="portal-pagination">{{ $pages->links('pagination::simple-tailwind') }}</div>@endif
            </section>
    @endswitch
@endsection
