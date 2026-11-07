{{-- Prima secțiune: mesajul și lentila. Cu WebGL, lentila e o scenă 3D (sticlă, marcajele reale de montaj, tabel
     optotip în spate, ilustrativ); fără, rămâne fotografia reală a lentilei din laborator. Adnotările merg în ambele. --}}
@php($img = $media[$d['image'] ?? 0] ?? null)
@php($s = app(\App\Core\Settings\Settings::class))
@php($dMin = $s->get('orders.production_days_min'))
@php($dMax = $s->get('orders.production_days_max'))
<section class="hero hero--lens">
    <div class="wrap hero__grid">
        <div class="hero__text" data-module="hero-intro">
            <h1 class="hero__title">{{ $d['title'] }}</h1>
            @if (! empty($d['subtitle']))<p class="lead">{{ $d['subtitle'] }}</p>@endif
            <div class="btn-row hero__actions">
                @if (! empty($d['primary']['url']))<a class="btn btn--primary btn--lg" href="{{ $d['primary']['url'] }}">{{ $d['primary']['label'] }}</a>@endif
                @if (! empty($d['secondary']['url']))<a class="btn btn--lg" href="{{ $d['secondary']['url'] }}">{{ $d['secondary']['label'] }}</a>@endif
            </div>
            @if (! empty($d['b2b_text']))
                <p class="hero__b2b">{{ $d['b2b_text'] }} @if(! empty($d['b2b_link']['url']))<a href="{{ $d['b2b_link']['url'] }}">{{ $d['b2b_link']['label'] }}</a>@endif</p>
            @endif
        </div>
        @if ($img)
            <figure class="lens-stage lens-anatomy" data-module="lens3d lens-anatomy" data-alt="Ilustrație: lentilă progresivă cu marcajele de montaj, în fața unui tabel optotip">
                <div class="lens-stage__photo"><x-site.picture :media="$img" class="lens-anatomy__img" :eager="$first" sizes="(min-width: 900px) 560px, 92vw" :alt="$d['caption'] ?? ''" /></div>
                @foreach ($d['pins'] ?? [] as $p => $pin)
                    <div class="lens-anatomy__pin lens-stage__pin--{{ $p + 1 }}" style="left: {{ max(5, min(95, (int) $pin['x'])) }}%; top: {{ max(5, min(95, (int) $pin['y'])) }}%" data-pin>
                        <button type="button" aria-expanded="false" aria-controls="adnotare-{{ $index }}-{{ $p }}">{{ $p + 1 }}<span class="sr-only">: {{ $pin['title'] }}</span></button>
                        <div class="lens-anatomy__note" id="adnotare-{{ $index }}-{{ $p }}" data-note hidden><strong>{{ $pin['title'] }}</strong>{{ $pin['text'] }}</div>
                    </div>
                @endforeach
                @if (! empty($d['caption']))<figcaption data-caption-3d="Ilustrație: lentilă progresivă cu marcajele de montaj, în fața unui tabel optotip. Mișcați mouse-ul (sau degetul) peste ea.">{{ $d['caption'] }}</figcaption>@endif
            </figure>
        @endif
    </div>
    <div class="wrap">
        <ul class="hero__facts">
            <li>Lentilele le facem în laboratorul nostru din Târgu Mureș.</li>
            <li>Un specialist optic verifică fiecare rețetă înainte de producție.</li>
            @if ($dMin && $dMax)<li>Producția durează {{ $dMin }}–{{ $dMax }} zile lucrătoare.</li>@endif
        </ul>
    </div>
</section>
