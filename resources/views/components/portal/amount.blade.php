@props(['minor'])
{{ $minor < 0 ? '−' : '' }}₦{{ number_format(abs($minor) / 100, 2) }}
