@props(['url'])

@php
    $brandUrl = $url ?: config('app.url');
@endphp

<tr>
<td class="header">
<a href="{{ $brandUrl }}" class="header-link">
<img src="{{ url('/images/logo.svg') }}" class="logo" alt="{{ config('app.name') }}">
</a>
</td>
</tr>
