@php($img = $media[$d['image'] ?? 0] ?? null)
<section class="hero">
    <div class="wrap hero__grid">
        <div>
            <h1>{{ $d['title'] }}</h1>
            @if (! empty($d['subtitle']))<p class="lead">{{ $d['subtitle'] }}</p>@endif
            <div class="btn-row hero__actions">
                @if (! empty($d['primary']['url']))<a class="btn btn--primary" href="{{ $d['primary']['url'] }}">{{ $d['primary']['label'] }}</a>@endif
                @if (! empty($d['secondary']['url']))<a class="btn" href="{{ $d['secondary']['url'] }}">{{ $d['secondary']['label'] }}</a>@endif
            </div>
            @if (! empty($d['b2b_text']))
                <p class="hero__b2b"><x-site.icon name="building" />{{ $d['b2b_text'] }} @if(! empty($d['b2b_link']['url']))<a href="{{ $d['b2b_link']['url'] }}">{{ $d['b2b_link']['label'] }}</a>@endif</p>
            @endif
        </div>
        @if ($img)
            <figure class="lens-anatomy" data-module="lens-anatomy">
                <x-site.picture :media="$img" class="lens-anatomy__img" :eager="$first" sizes="(min-width: 900px) 560px, 92vw" :alt="$d['caption'] ?? ''" />
                @foreach ($d['pins'] ?? [] as $p => $pin)
                    <div class="lens-anatomy__pin" style="left: {{ max(5, min(95, (int) $pin['x'])) }}%; top: {{ max(5, min(95, (int) $pin['y'])) }}%" data-pin>
                        <button type="button" aria-expanded="false" aria-controls="adnotare-{{ $index }}-{{ $p }}">{{ $p + 1 }}<span class="sr-only">: {{ $pin['title'] }}</span></button>
                        <div class="lens-anatomy__note" id="adnotare-{{ $index }}-{{ $p }}" data-note hidden><strong>{{ $pin['title'] }}</strong>{{ $pin['text'] }}</div>
                    </div>
                @endforeach
                @if (! empty($d['caption']))<figcaption>{{ $d['caption'] }}</figcaption>@endif
            </figure>
        @endif
    </div>
</section>
