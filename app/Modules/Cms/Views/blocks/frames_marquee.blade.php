{{-- Bandă cu fotografii reale de rame, care derulează încet (se oprește la hover și la „reducere mișcare”). --}}
@php($items = app(\App\Modules\Storefront\Services\ProductShowcase::class)->frames(null, max(8, min(30, (int) ($d['limit'] ?? 16)))))
@if ($items->count() >= 4)
<section class="section section--light marquee-section">
    <div class="wrap">
        @if (! empty($d['title']))<h2 data-reveal>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead" data-reveal>{{ $d['intro'] }}</p>@endif
    </div>
    <div class="marquee" aria-label="Rame din catalog">
        <div class="marquee__track">
            @foreach ([$items, $items] as $round => $list)
                @foreach ($list as $card)
                    <a class="marquee__item" href="{{ $card['url'] }}" @if($round === 1) aria-hidden="true" tabindex="-1" @endif>
                        <x-site.picture :media="$card['image']" :alt="$round === 0 ? $card['name'] : ''" sizes="220px" />
                        <span>{{ $card['brand'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>
    @if (! empty($d['cta']['url']))<div class="wrap"><p style="margin-top: 20px"><a class="btn" href="{{ $d['cta']['url'] }}">{{ $d['cta']['label'] }}</a></p></div>@endif
</section>
@endif
