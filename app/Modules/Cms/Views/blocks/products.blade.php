@php($items = app(\App\Modules\Storefront\Services\ProductShowcase::class)->frames($d['brand'] ?? null, (int) ($d['limit'] ?? 8), ($d['gen'] ?? '') ?: null))
@php($carousel = ($d['layout'] ?? '') === 'carusel')
@if ($items->isNotEmpty())
<section class="section section--products">
    <div class="wrap">
        <div class="section-head">
            <div>
                @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
                @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
            </div>
            @if (! empty($d['cta']['url']))<a class="btn" href="{{ $d['cta']['url'] }}">{{ $d['cta']['label'] }}</a>@endif
        </div>
        @if ($carousel)
            <div class="shelf" data-module="carousel">
                <div class="shelf__track" data-carousel-track>
                    @foreach ($items as $card)<div class="shelf__item" data-carousel-slide>@include('storefront::partials.card', ['card' => $card])</div>@endforeach
                </div>
                <button class="shelf__arrow shelf__arrow--prev" type="button" data-carousel-prev aria-label="Produsele anterioare"><x-site.icon name="chevron-left" /></button>
                <button class="shelf__arrow shelf__arrow--next" type="button" data-carousel-next aria-label="Produsele următoare"><x-site.icon name="chevron-right" /></button>
            </div>
        @else
            <div class="grid grid--4">
                @foreach ($items as $card)@include('storefront::partials.card', ['card' => $card])@endforeach
            </div>
        @endif
    </div>
</section>
@endif
