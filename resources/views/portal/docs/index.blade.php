<nav class="portal-tabs" aria-label="API versions">
    @foreach (['v1', 'v2', 'v3'] as $item)
        <a href="{{ route('portal.page', 'docs-'.$item) }}" @class(['portal-btn', 'portal-tab-active' => $version === $item]) @if ($version === $item) aria-current="page" @endif>API {{ strtoupper($item) }}</a>
    @endforeach
</nav>
<section class="portal-panel">
    <header class="portal-panel-head"><h2>API {{ strtoupper($version) }} services</h2></header>
    <div class="portal-table-wrap">
        <table class="portal-table">
            <thead><tr><th>Service</th><th>Endpoint</th><th>Documentation</th></tr></thead>
            <tbody>
                @foreach ($services as $service => $name)
                    <tr>
                        <td>{{ $name }}</td>
                        <td>
                            @if ($version === 'v1' && $service === 'ipeclearing')
                                <x-portal.endpoint route="api.clearance.submit" /><br>
                                <x-portal.endpoint route="api.clearance.status" />
                            @elseif ($version === 'v1' && $service === 'personalization')
                                <x-portal.endpoint route="api.personalization.submit" /><br>
                                <x-portal.endpoint route="api.personalization.check" />
                            @elseif (in_array($version, ['v1', 'v2'], true))
                                <x-portal.endpoint :route="'api.'.$version.'.'.$service" />
                            @else
                                Pending
                            @endif
                        </td>
                        <td>
                            <a class="portal-link" href="{{ route('portal.docs.service', [$version, $service]) }}" aria-label="Open documentation for {{ $name }}">Open documentation <x-portal.icon name="arrow" /></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
