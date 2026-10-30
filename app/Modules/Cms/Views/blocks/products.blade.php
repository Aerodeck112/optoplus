@php($items = app(\App\Modules\Storefront\Services\ProductShowcase::class)->frames($d['brand'] ?? null, (int) ($d['limit'] ?? 8)))
@if ($items->isNotEmpty())
<section class="section">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
        <div class="grid grid--4">
            @foreach ($items as $card)
                @include('storefront::partials.card', ['card' => $card])
            @endforeach
        </div>
        @if (! empty($d['cta']['url']))<p style="margin-top: 32px"><a class="btn" href="{{ $d['cta']['url'] }}">{{ $d['cta']['label'] }}</a></p>@endif
    </div>
</section>
@endif
