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
        <label>Price (kobo)<input type="number" name="price_minor" min="0" max="1000000000" value="{{ $price->price_minor ?? old('price_minor') }}" required></label>
        <label>Error charge (kobo)<input type="number" name="error_charge_minor" min="0" max="1000000000" value="{{ $price->error_charge_minor ?? old('error_charge_minor', 0) }}" required></label>
        <label>Availability<select name="available"><option value="0">Unavailable</option><option value="1" @selected($price->available ?? old('available'))>Available</option></select></label>
    </div>
    <div><button class="portal-btn portal-btn-primary">Save pricing</button></div>
</form>
