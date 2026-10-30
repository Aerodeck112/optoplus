<a class="metric" href="{{ $url }}" style="text-decoration:none;display:block">
    <span class="metric__label">{{ $label }}</span>
    <span class="metric__value">{{ $value }}</span>
    @if ($help)<span class="metric__help">{{ $help }}</span>@endif
</a>
