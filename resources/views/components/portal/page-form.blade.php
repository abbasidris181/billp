@props(['category', 'page' => null])
<form class="portal-panel-body portal-form" method="POST" action="{{ route('admin.publish', $category) }}">
    @csrf
    <div class="portal-form-grid">
        <label>{{ $category === 'docs' ? 'API version' : 'Page slug' }}
            @if($page)
                <input name="slug" value="{{ Str::after($page->slug, $category.'-') }}" readonly>
            @elseif($category === 'docs')
                <select name="slug">@foreach(['v1','v2','v3'] as $version)<option value="{{ $version }}" @selected(old('slug') === $version)>{{ strtoupper($version) }}</option>@endforeach</select>
            @else
                <input name="slug" value="{{ old('slug') }}" maxlength="80" placeholder="business-verification" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required>
            @endif
        </label>
        <label>Title<input name="title" value="{{ $page->title ?? old('title') }}" maxlength="255" required></label>
    </div>
    <label>Page content<textarea name="body" rows="7" maxlength="30000" required>{{ $page->body ?? old('body') }}</textarea></label>
    <div><button class="portal-btn portal-btn-primary">Publish page</button></div>
</form>
