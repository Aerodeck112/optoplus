@extends('layouts.site')
@section('title', $product->seo_title ?: $product->name)
@section('description', $product->seo_description ?: \App\Core\Support\Html::plain($product->short_description ?: ($product->brand?->name.' — ramă de ochelari de vedere'.($spec?->sizeNotation() ? ', mărime '.$spec->sizeNotation() : '').'. Comand-o simplă sau cu lentile produse în laboratorul Optoplus.'), 155))
@section('og_type', 'product')
@if ($img = $product->images->first()?->media)@section('og_image', url($img->bestUrl(1200)))@endif
@if ($product->noindex)@section('robots', 'noindex, follow')@endif
@section('content')
@php($price = $default['price'] ?? null)
<div class="wrap">
    <x-site.breadcrumbs :items="array_filter(['Ochelari și rame' => '/rame/', $product->brand?->name => $product->brand?->url(), $product->name => null])" />
    <div class="product">
        <div>
            <div class="gallery" data-module="gallery">
                @php($first = $product->images->first())
                <div class="gallery__main" data-gallery-main>
                    <x-site.picture :media="$first?->media" :alt="$product->name" :eager="true" sizes="(min-width: 1000px) 640px, 100vw" />
                </div>
                @if ($product->images->count() > 1)
                    <div class="gallery__thumbs" role="list" aria-label="Alte imagini">
                        @foreach ($product->images as $i => $image)
                            @if ($image->media)
                                <button type="button" role="listitem" data-gallery-thumb aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                        data-src="{{ $image->media->bestUrl(960) }}" data-srcset="{{ $image->media->srcset() }}" data-alt="{{ $product->name }} — imaginea {{ $i + 1 }}">
                                    <img src="{{ $image->media->bestUrl(320) }}" alt="" width="76" height="64" loading="lazy">
                                    <span class="sr-only">Arată imaginea {{ $i + 1 }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif
                <p class="small muted" style="margin-top: 8px">Atingeți imaginea pentru a o mări.</p>
            </div>

            @if ($spec && $spec->lens_width_mm)
                <section class="frame-dims" style="margin-top: 32px" aria-labelledby="dimensiuni">
                    <h2 id="dimensiuni" style="font-size: 1.4rem">Dimensiunile ramei</h2>
                    @include('storefront::partials.frame-dims', ['spec' => $spec])
                    <p class="small muted">Comparați cu cifrele gravate pe brațul ochelarilor pe care îi purtați (ex. {{ $spec->sizeNotation() }}). O diferență de 1–2 mm la lățime este de obicei confortabilă.</p>
                </section>
            @endif
        </div>

        <div class="buy">
            @if ($product->brand)<p class="buy__brand"><a href="{{ $product->brand->url() }}">{{ $product->brand->name }}</a>@if($product->brand->is_own_brand) · marcă proprie @endif</p>@endif
            <h1>{{ $product->name }}</h1>
            <div class="buy__price">
                @if ($price !== null)<x-site.price :amount="$price" /><small>prețul ramei, fără lentile</small>@endif
            </div>
            @if ($withLenses)
                <p class="split-note"><x-site.icon name="lens" /><span>Cu lentile produse în laboratorul nostru: de la <strong>{{ \App\Core\Support\Money::format($withLenses) }}</strong> (rama + o pereche de lentile monofocale). Prețul final îl vedeți la fiecare pas al configurării.</span></p>
            @endif
            <div class="buy__box">
                <form method="post" action="/configurator/start/" class="form" id="buy-form">
                    @if ($variants->count() > 1)
                        <fieldset class="variants">
                            <legend>Culoare</legend>
                            @foreach ($variants as $v)
                                <label><input type="radio" name="varianta" value="{{ $v['model']->id }}" @checked($loop->first) required>{{ $v['model']->name ?: $v['model']->sku }} · <x-site.price :amount="$v['price']" /></label>
                            @endforeach
                        </fieldset>
                    @else
                        <input type="hidden" name="varianta" value="{{ $default['model']->id ?? '' }}">
                    @endif
                    <input type="hidden" name="intrare" value="cu_rama">
                    @if ($product->configurable_with_lenses)
                        <button class="btn btn--primary btn--block" type="submit"><x-site.icon name="lens" />Alege lentilele pentru această ramă</button>
                    @endif
                    <button class="btn btn--block" type="submit" formaction="/cos/adauga/"><x-site.icon name="bag" />Adaugă în coș doar rama</button>
                    @csrf
                </form>
                <dl class="facts">
                    <dt>Disponibilitate</dt><dd>{{ $product->availabilityLabel() }}@if($product->lead_time_days_max) · pregătire {{ $product->lead_time_days_min }}–{{ $product->lead_time_days_max }} zile lucrătoare @endif</dd>
                    @if ($default)<dt>Cod produs</dt><dd>{{ $default['model']->sku_generated ? '—' : $default['model']->sku }}</dd>@endif
                    @foreach ($attrs as $a)<dt>{{ $a['label'] }}</dt><dd>{{ implode(', ', $a['values']) }}</dd>@endforeach
                    @if ($spec?->sizeNotation())<dt>Mărime</dt><dd>{{ $spec->sizeNotation() }}@if($spec->size_label) ({{ $spec->size_label }})@endif</dd>@endif
                </dl>
                <p class="small muted" style="margin: 0"><x-site.icon name="truck" :size="18" /> Livrarea și costul ei se văd în coș, înainte de plată. Timpul de producție al lentilelor se adaugă celui de transport.</p>
            </div>
            @if ($product->short_description || $product->description)
                <section class="prose" aria-labelledby="descriere">
                    <h2 id="descriere" style="font-size: 1.4rem">Despre ramă</h2>
                    {!! $product->short_description !!}
                    {!! $product->description !!}
                </section>
            @endif
            @if ($faqs->isNotEmpty())
                <section class="faq" aria-labelledby="intrebari">
                    <h2 id="intrebari" style="font-size: 1.4rem">Întrebări utile</h2>
                    @foreach ($faqs as $faq)<details><summary>{{ $faq->question }}<x-site.icon name="chevron-down" /></summary><div>{!! $faq->answer !!}</div></details>@endforeach
                </section>
            @endif
        </div>
    </div>
    @if ($similar->isNotEmpty())
        <section class="section" style="padding-top: 0" aria-labelledby="similare">
            <h2 id="similare">Alte rame {{ $product->brand?->name }}</h2>
            <div class="grid grid--4">@foreach ($similar as $card)@include('storefront::partials.card', ['card' => $card])@endforeach</div>
        </section>
    @endif
</div>
@php($ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'url' => url($product->url()),
    'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
    'image' => $product->images->filter(fn ($i) => $i->media)->map(fn ($i) => url($i->media->bestUrl(1200)))->values()->all(),
    'description' => \App\Core\Support\Html::plain($product->short_description ?: $product->description, 500) ?: null,
    'sku' => ($default && ! $default['model']->sku_generated) ? $default['model']->sku : null,
    'offers' => $variants->map(fn ($v) => ['@type' => 'Offer', 'price' => number_format($v['price'] / 100, 2, '.', ''), 'priceCurrency' => 'RON', 'sku' => $v['model']->sku_generated ? null : $v['model']->sku, 'url' => url($product->url()),
        'availability' => match ($product->availability_mode) { 'stoc_propriu' => 'https://schema.org/InStock', 'furnizor', 'la_comanda' => 'https://schema.org/BackOrder', 'indisponibil' => 'https://schema.org/OutOfStock', default => null }])->all()])
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(array_filter($ld, fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ochelari și rame', 'item' => url('/rame/')],
    $product->brand ? ['@type' => 'ListItem', 'position' => 2, 'name' => $product->brand->name, 'item' => url($product->brand->url())] : null,
    ['@type' => 'ListItem', 'position' => $product->brand ? 3 : 2, 'name' => $product->name],
]))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@endsection
