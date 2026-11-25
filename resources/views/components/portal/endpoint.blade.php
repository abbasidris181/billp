@props(['route'])
<code>POST {{ rtrim(config('app.url'), '/').route($route, absolute: false) }}</code>
