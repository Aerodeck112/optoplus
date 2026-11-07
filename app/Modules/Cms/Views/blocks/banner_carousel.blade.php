{{-- Bannere mari care se schimbă (carusel). Primul banner poartă titlul paginii (h1). Fără JavaScript, bannerele
     se derulează orizontal cu degetul / bara de derulare. Ilustrațiile sunt desene, nu persoane reale. --}}
@php($slides = array_values(array_filter($d['slides'] ?? [], fn ($s) => ! empty($s['title']))))
@if ($slides)
<section class="banners" data-module="carousel" data-carousel-autoplay="7000" aria-roledescription="carusel" aria-label="Noutăți Optoplus">
    <div class="wrap">
        <div class="banners__track" data-carousel-track>
            @foreach ($slides as $i => $s)
                @php($photo = $media[$s['photo'] ?? 0] ?? null)
                @php($prod = $media[$s['product'] ?? 0] ?? null)
                @php($prod2 = $media[$s['product2'] ?? 0] ?? null)
                <article class="banner banner--{{ $s['background'] ?: 'bleu' }}" data-carousel-slide aria-roledescription="slide" aria-label="{{ $i + 1 }} din {{ count($slides) }}">
                    <div class="banner__text">
                        @if (! empty($s['badge']))<p class="banner__badge">{{ $s['badge'] }}</p>@endif
                        @if ($i === 0 && $first)<h1 class="banner__title">{{ $s['title'] }}</h1>@else<h2 class="banner__title">{{ $s['title'] }}</h2>@endif
                        @if (! empty($s['text']))<p class="banner__lead">{{ $s['text'] }}</p>@endif
                        <div class="btn-row">
                            @if (! empty($s['primary']['url']))<a class="btn btn--primary btn--lg" href="{{ $s['primary']['url'] }}">{{ $s['primary']['label'] }}</a>@endif
                            @if (! empty($s['secondary']['url']))<a class="btn btn--lg banner__btn2" href="{{ $s['secondary']['url'] }}">{{ $s['secondary']['label'] }}</a>@endif
                        </div>
                    </div>
                    <div class="banner__visual">
                        <span class="banner__disc" aria-hidden="true"></span>
                        @if ($photo)
                            <x-site.picture :media="$photo" class="banner__photo" :eager="$i === 0 && $first" sizes="(min-width: 900px) 560px, 90vw" alt="" />
                        @elseif (! empty($s['illustration']))
                            <div class="banner__person">@include('cms::illustrations.person', ['variant' => $s['illustration']])</div>
                        @endif
                        @if ($prod)<div class="banner__product"><x-site.picture :media="$prod" :eager="$i === 0 && $first" sizes="(min-width: 900px) 300px, 45vw" alt="" /></div>@endif
                        @if ($prod2)<div class="banner__product banner__product--2"><x-site.picture :media="$prod2" sizes="(min-width: 900px) 220px, 35vw" alt="" /></div>@endif
                    </div>
                </article>
            @endforeach
        </div>
        @if (count($slides) > 1)
            <div class="banners__nav">
                <button class="banners__arrow" type="button" data-carousel-prev aria-label="Bannerul anterior"><x-site.icon name="chevron-left" /></button>
                <div class="banners__dots" role="group" aria-label="Alege bannerul">
                    @foreach ($slides as $i => $s)<button type="button" data-carousel-dot="{{ $i }}" aria-label="Bannerul {{ $i + 1 }}: {{ $s['title'] }}" @if($i === 0) aria-current="true" @endif></button>@endforeach
                </div>
                <button class="banners__arrow" type="button" data-carousel-next aria-label="Bannerul următor"><x-site.icon name="chevron-right" /></button>
            </div>
        @endif
    </div>
</section>
@endif
