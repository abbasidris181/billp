@props(['price' => null])
<form class="portal-panel-body portal-form" method="POST" action="{{ route('admin.price') }}">
    @csrf
    <div class="portal-form-grid">
        <label>Service<input name="service" value="{{ $price->service ?? old('service') }}" maxlength="100" required @readonly($price)></label>
        <label>API version
            @if($price)
                <input name="version" value="{{ $price->version }}" readonly>
            @else
                <select name="version">@foreach(['v1','v2','v3'] as $version)<option value="{{ $version }}" @selected(old('version') === $version)>{{ strtoupper($version) }}</option>@endforeach</select>
            @endif
        </label>
        <label>Price (₦)<input type="number" name="price" min="0" max="10000000" step="0.01" value="{{ isset($price->price_minor) ? number_format($price->price_minor / 100, 2, '.', '') : old('price') }}" required></label>
        <label>Error charge (₦, optional)<input type="number" name="error_charge" min="0" max="10000000" step="0.01" value="{{ !empty($price->error_charge_minor) ? number_format($price->error_charge_minor / 100, 2, '.', '') : old('error_charge') }}"></label>
        <label>Availability<select name="available"><option value="0">Unavailable</option><option value="1" @selected($price->available ?? old('available'))>Available</option></select></label>
    </div>
    <div><button class="portal-btn portal-btn-primary">Save pricing</button></div>
</form>
