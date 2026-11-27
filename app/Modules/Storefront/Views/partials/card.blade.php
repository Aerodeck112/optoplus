{{-- Card de ramă: imagine, marcă, nume, dimensiuni, preț (din motorul de preț). --}}
<article class="card">
    <div class="card__img"><x-site.picture :media="$card['image']" :alt="$card['name']" sizes="(min-width: 1300px) 280px, (min-width: 700px) 30vw, 46vw" /></div>
    @if ($card['brand'])<span class="card__brand">{{ $card['brand'] }}</span>@endif
    <h3 class="card__name"><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></h3>
    @if ($card['size'])<span class="card__meta">Mărime {{ $card['size'] }}@if($card['variants'] > 1) · {{ $card['variants'] }} culori @endif</span>@endif
    {{-- 1.0.21: „Editează” pe fiecare ramă, doar pentru colegii autentificați în panou (pagina nu intră în cache). --}}
    @php($canEdit = (bool) auth('web')->user()?->can('catalog.view'))
    @if ($canEdit && ($card['product'] ?? null))
        <a class="card__edit" href="{{ route('panou.catalog.product', $card['product']->id) }}" data-no-localize translate="no" lang="ro" title="Editează rama în panou">✎ Editează</a>
    @endif
    @if ($card['price'] !== null)
        <span class="card__price">@if($card['variants'] > 1)<span class="price__from">de la</span> @endif @if (! empty($card['reduced']))<del class="price-was"><x-site.price :amount="$card['reduced']['was']" /></del> @endif<x-site.price :amount="$card['price']" /><span class="price__unit"> rama</span>@if (! empty($card['reduced']))<span class="sale-badge">−{{ $card['reduced']['percent'] }}%</span>@endif</span>
    @endif
</article>
