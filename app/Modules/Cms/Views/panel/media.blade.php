@extends('layouts.panou')
@section('title', 'Bibliotecă media')
@section('content')
    <x-panou.page-head title="Bibliotecă media" sub="Imaginile se reencodează la încărcare (fără metadate GPS); se generează automat variante WebP. Folosiți ID-ul imaginii în blocurile paginilor." />
    @can('cms.media')
    <x-panou.panel title="Încarcă imagini">
        <form method="post" action="{{ route('panou.cms.media.upload') }}" enctype="multipart/form-data" class="form-grid">@csrf
            <div class="field"><label class="field__label" for="fisiere">Imagini (JPG, PNG, WebP, GIF; max. 10 MB fiecare)</label><input id="fisiere" type="file" name="fisiere[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required></div>
            <x-panou.field name="dosar" type="select" label="Dosar" :options="['pagini' => 'Pagini', 'productie' => 'Producție / laborator', 'general' => 'General', 'lentile' => 'Lentile']" value="pagini" :placeholder="false" />
            <x-panou.field name="alt" label="Text alternativ (descrierea imaginii)" />
            <div class="form-actions"><button class="btn btn--primary" type="submit">Încarcă</button></div>
        </form>
    </x-panou.panel>
    @endcan
    <form class="filters" method="get" role="search">
        <x-panou.field name="q" type="search" label="Nume, text alternativ sau ID" :value="$filters['q'] ?? ''" />
        <x-panou.field name="dosar" type="select" label="Dosar" :options="$folders->mapWithKeys(fn ($f) => [$f => $f])->all()" :value="$filters['dosar'] ?? ''" placeholder="toate" />
        <button class="btn" type="submit">Caută</button>
    </form>
    <div class="media-grid">
        @foreach ($items as $m)
            <figure class="media-card">
                <img src="{{ $m->bestUrl(320) }}" alt="" loading="lazy" width="160" height="120">
                <figcaption>
                    <strong>ID {{ $m->id }}</strong> <span class="small muted">{{ $m->folder }} · {{ $m->width }}×{{ $m->height }}</span>
                    @can('cms.media')
                    <form method="post" action="{{ route('panou.cms.media.update', $m->id) }}" class="stack">@csrf
                        <input name="alt" value="{{ $m->alt }}" placeholder="text alternativ" aria-label="Text alternativ pentru imaginea {{ $m->id }}">
                        <input name="legenda" value="{{ $m->caption }}" placeholder="legendă (opțional)" aria-label="Legendă pentru imaginea {{ $m->id }}">
                        <div class="cluster"><button class="btn btn--small" type="submit">Salvează</button></div>
                    </form>
                    <form method="post" action="{{ route('panou.cms.media.destroy', $m->id) }}">@csrf<button class="link-btn small">Șterge</button></form>
                    @endcan
                </figcaption>
            </figure>
        @endforeach
    </div>
    {{ $items->links() }}
@endsection
