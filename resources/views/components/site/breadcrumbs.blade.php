@props(['items' => []])
<nav class="crumbs" aria-label="Unde vă aflați">
    <ol>
        <li><a href="/">Acasă</a></li>
        @foreach ($items as $label => $url)
            <li>@if($url && ! $loop->last)<a href="{{ $url }}">{{ $label }}</a>@else<span aria-current="page">{{ $label }}</span>@endif</li>
        @endforeach
    </ol>
</nav>
