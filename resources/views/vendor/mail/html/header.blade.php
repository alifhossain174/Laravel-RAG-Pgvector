@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-link" target="_blank" rel="noopener">
<span class="brand-mark">D</span>
<span class="brand-name">{!! $slot !!}</span>
</a>
</td>
</tr>
