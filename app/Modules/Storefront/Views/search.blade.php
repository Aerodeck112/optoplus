@extends('layouts.site')
@section('title', $q !== '' ? 'Căutare: '.$q : 'Căutare')
@section('robots', 'noindex, follow')
@section('content')
<div class="wrap">
    <x-site.breadcrumbs :items="['Căutare' => null]" />
    <header class="page-head">
        <h1>{{ $q !== '' ? 'Rezultate pentru „'.$q.'”' : 'Caută în catalog' }}</h1>
        <form class="form" action="/cautare/" method="get" role="search" style="max-width: 560px">
            <div class="field"><label for="q">Nume, marcă, cod de produs sau tip de lentilă</label>
                <div class="btn-row" style="flex-wrap: nowrap"><input id="q" type="search" name="q" value="{{ $q }}" placeholder="ex. Ray-Ban, 0RX6550, progresive, rame copii"><button class="btn btn--primary" type="submit">Caută</button></div></div>
        </form>
    </header>
    @if ($q !== '')
        @if ($total === 0)
            <div class="empty">
                <h2>Nu am găsit nimic pentru „{{ $q }}”</h2>
                <p>Verificați scrierea sau încercați un termen mai general (marca, materialul, „copii”). Vă putem ajuta și la telefon.</p>
                <div class="btn-row" style="justify-content: center"><a class="btn" href="/rame/">Toate ramele</a><a class="btn" href="/lentile/">Toate lentilele</a></div>
            </div>
        @else
            @if ($lenses->isNotEmpty())
                <section class="section" style="padding-top: 0"><h2>Lentile</h2>
                    <div class="entry-grid">@foreach ($lenses as $p)<a class="entry" href="{{ $p->url() }}"><strong>{{ $p->name }}</strong><span>{{ $p->lens?->customer_summary }}</span></a>@endforeach</div>
                </section>
            @endif
            @if ($cards->isNotEmpty())
                <section class="section" style="padding-top: 0"><h2>Rame ({{ $frames->total() }})</h2>
                    <div class="grid grid--4">@foreach ($cards as $card)@include('storefront::partials.card', ['card' => $card])@endforeach</div>
                    {{ $frames->onEachSide(1)->links('storefront::partials.pagination') }}
                </section>
            @endif
        @endif
    @endif
</div>
@endsection
