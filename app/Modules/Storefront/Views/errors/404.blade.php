@extends('layouts.site')
@section('title', 'Pagina nu există')
@section('robots', 'noindex, follow')
@section('content')
<div class="wrap section" style="padding-top: 32px">
    <h1>Pagina căutată nu există</h1>
    <p class="lead">Este posibil ca adresa să se fi schimbat odată cu noul site. Căutați produsul sau mergeți direct la catalog.</p>
    <form class="form" action="/cautare/" method="get" role="search" style="max-width: 560px">
        <div class="field"><label for="q404">Caută rame sau lentile</label><div class="btn-row" style="flex-wrap: nowrap"><input id="q404" type="search" name="q" value="{{ $q }}"><button class="btn btn--primary" type="submit">Caută</button></div></div>
    </form>
    <div class="btn-row" style="margin: 24px 0 48px"><a class="btn" href="/rame/">Ochelari și rame</a><a class="btn" href="/lentile/">Lentile</a><a class="btn" href="/configurator/">Configurează ochelarii</a><a class="btn" href="/contact/">Contact</a></div>
    @if ($cards->isNotEmpty())
        <h2>Rame noi în catalog</h2>
        <div class="grid grid--4">@foreach ($cards as $card)@include('storefront::partials.card', ['card' => $card])@endforeach</div>
    @endif
</div>
@endsection
