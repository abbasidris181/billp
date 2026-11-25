@props(['name'])
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/> @break
        @case('list') <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6m-6 4h6m-6 4h4"/> @break
        @case('wallet') <path d="M20 8H5a2 2 0 0 1 0-4h13v4M3 6v12a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1m1 4h-5a2 2 0 0 0 0 4h5"/> @break
        @case('tag') <path d="M3 5v6l9 9a2 2 0 0 0 3 0l5-5a2 2 0 0 0 0-3l-9-9H5a2 2 0 0 0-2 2Z"/><path d="M7.5 7.5h.01"/> @break
        @case('code') <path d="m8 6-6 6 6 6m8-12 6 6-6 6m-3-15-2 18"/> @break
        @case('shield') <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/> @break
        @case('settings') <path d="M4 6h16M4 12h16M4 18h16"/><path d="M9 4v4m6 2v4m-7 2v4"/> @break
        @case('logout') <path d="M10 17l5-5-5-5m5 5H3m13-8h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('check') <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/> @break
        @case('fail') <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('arrow') <path d="M4 12h15m-6-6 6 6-6 6"/> @break
    @endswitch
</svg>
