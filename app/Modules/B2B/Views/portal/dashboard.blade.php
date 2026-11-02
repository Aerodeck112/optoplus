@extends('b2b::portal.shell')
@section('title', 'Portal parteneri')
@section('heading', 'Bună, '.($partner->customer->first_name ?: $org->name))
@section('body')
<div class="stat-row stat-row--portal">
    <a class="stat" href="/portal/cos/"><span class="stat__icon"><x-site.icon name="bag" :size="22" /></span><strong>{{ $cartCount }}</strong><span>{{ $cartCount === 1 ? 'lucrare în coș' : 'lucrări în coș' }}</span></a>
    <a class="stat" href="/portal/comenzi/"><span class="stat__icon stat__icon--lab"><x-site.icon name="lab" :size="22" /></span><strong>{{ $inProduction }}</strong><span>{{ $inProduction === 1 ? 'comandă în lucru' : 'comenzi în lucru' }}</span></a>
    <a class="stat" href="/portal/oferte/"><span class="stat__icon stat__icon--quote"><x-site.icon name="file" :size="22" /></span><strong>{{ $openQuotes }}</strong><span>{{ $openQuotes === 1 ? 'ofertă deschisă' : 'oferte deschise' }}</span></a>
</div>
@if ($partner->can('order'))
<div class="entry-grid entry-grid--portal">
    <a class="entry entry--feature" href="/portal/comanda-rapida/">
        <span class="entry__icon"><x-site.icon name="lens" :size="26" /></span>
        <strong>Comandă rapidă</strong><span>Mai multe lucrări odată, fiecare cu codul intern al pacientului. Validare și preț pe loc.</span>
        <em class="entry__go">Începe o comandă <x-site.icon name="chevron-right" :size="16" /></em>
    </a>
    <a class="entry" href="/portal/import/">
        <span class="entry__icon entry__icon--alt"><x-site.icon name="upload" :size="26" /></span>
        <strong>Import din CSV</strong><span>Încărcați un fișier după model; vedeți pe rânduri ce trebuie corectat.</span>
        <em class="entry__go">Încarcă fișierul <x-site.icon name="chevron-right" :size="16" /></em>
    </a>
    <a class="entry" href="/portal/oferte/">
        <span class="entry__icon entry__icon--warm"><x-site.icon name="chat" :size="26" /></span>
        <strong>Cerere de ofertă</strong><span>Pentru volume sau lucrări speciale: primiți oferta, cu versiuni, și o acceptați online.</span>
        <em class="entry__go">Cere o ofertă <x-site.icon name="chevron-right" :size="16" /></em>
    </a>
</div>
@endif
<section class="box box--portal">
    <div class="box__head"><h2>Comenzi recente</h2>@if ($recent->isNotEmpty())<a href="/portal/comenzi/">Toate comenzile</a>@endif</div>
    @if ($recent->isEmpty())
        <div class="portal-empty">
            <div class="portal-empty__visual">@include('b2b::mock.screen', ['screen' => 'tracking', 'chrome' => false])</div>
            <div>
                <h3>Prima comandă, în câteva minute</h3>
                <p>După trimitere, fiecare lucrare apare aici cu etapa în care se află: verificarea rețetei de către specialist, producția în laborator, controlul de calitate și expedierea.</p>
                @if ($partner->can('order'))<p><a class="btn btn--primary" href="/portal/comanda-rapida/">Fă prima comandă rapidă</a></p>@endif
                <p class="small muted">Ilustrația folosește date fictive.</p>
            </div>
        </div>
    @else
        @include('b2b::portal.partials.orders-table', ['orders' => $recent])
    @endif
</section>
<p class="small muted">
    @if (\App\Core\Support\Tax::payer())
        Prețurile din portal sunt cele din lista dumneavoastră de preț și se afișează {{ $partner->showVat() ? 'cu TVA' : 'fără TVA' }}.
    @else
        Prețurile din portal sunt cele din lista dumneavoastră de preț; sunt finale (firma nu este plătitoare de TVA).
    @endif
    Lentilele sunt produse în laboratorul Optoplus după verificarea fiecărei rețete de către un specialist optic.
</p>
@endsection
