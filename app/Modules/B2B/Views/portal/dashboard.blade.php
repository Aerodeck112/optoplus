@extends('b2b::portal.shell')
@section('title', 'Portal parteneri')
@section('heading', 'Bună, '.($partner->customer->first_name ?: $org->name))
@section('body')
<div class="stat-row">
    <a class="stat" href="/portal/cos/"><strong>{{ $cartCount }}</strong><span>lucrări în coș</span></a>
    <a class="stat" href="/portal/comenzi/"><strong>{{ $inProduction }}</strong><span>comenzi în lucru</span></a>
    <a class="stat" href="/portal/oferte/"><strong>{{ $openQuotes }}</strong><span>oferte deschise</span></a>
</div>
@if ($partner->can('order'))
<div class="entry-grid">
    <a class="entry" href="/portal/comanda-rapida/"><x-site.icon name="lens" :size="28" /><strong>Comandă rapidă</strong><span>Mai multe lucrări odată, fiecare cu codul intern al pacientului. Validare și preț pe loc.</span></a>
    <a class="entry" href="/portal/import/"><x-site.icon name="upload" :size="28" /><strong>Import din CSV</strong><span>Încărcați un fișier după model; vedeți pe rânduri ce trebuie corectat.</span></a>
</div>
@endif
<section class="box" style="margin-top: 24px">
    <h2>Comenzi recente</h2>
    @include('b2b::portal.partials.orders-table', ['orders' => $recent])
</section>
<p class="small muted">Prețurile din portal sunt cele din lista dumneavoastră de preț și se afișează {{ $partner->showVat() ? 'cu TVA' : 'fără TVA' }}. Lentilele sunt produse în laboratorul Optoplus după verificarea fiecărei rețete de către un specialist optic.</p>
@endsection
