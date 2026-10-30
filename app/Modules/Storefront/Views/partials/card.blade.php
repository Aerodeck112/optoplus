{{-- Card de ramă: imagine, marcă, nume, dimensiuni, preț (din motorul de preț). --}}
<article class="card">
    <div class="card__img"><x-site.picture :media="$card['image']" :alt="$card['name']" sizes="(min-width: 1300px) 280px, (min-width: 700px) 30vw, 46vw" /></div>
    @if ($card['brand'])<span class="card__brand">{{ $card['brand'] }}</span>@endif
    <h3 class="card__name"><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></h3>
    @if ($card['size'])<span class="card__meta">Mărime {{ $card['size'] }}@if($card['variants'] > 1) · {{ $card['variants'] }} culori @endif</span>@endif
    @if ($card['price'] !== null)
        <span class="card__price">@if($card['variants'] > 1)<span class="price__from">de la</span> @endif<x-site.price :amount="$card['price']" /><span class="price__unit"> rama</span></span>
    @endif
</article>
