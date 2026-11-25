@props(['route', 'match' => '', 'icon'])
@php($active = request()->routeIs($route) || ($match && request()->routeIs($match)))
<a href="{{ route($route) }}" @class(['portal-nav-link', 'portal-nav-active' => $active]) @if($active) aria-current="page" @endif>
    <x-portal.icon :name="$icon" /> {{ $slot }}
</a>
