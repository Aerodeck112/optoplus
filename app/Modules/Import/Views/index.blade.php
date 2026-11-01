@extends('layouts.panou')
@section('title', 'Import WooCommerce')
@section('content')
    <x-panou.page-head title="Import catalog (WooCommerce)" sub="Încărcați un export de produse din WooCommerce. Întâi se face o simulare (nu scrie nimic); importul efectiv actualizează produsele existente fără duplicate." />
    <div class="grid grid--2">
        <x-panou.panel title="Import nou">
            <form method="post" action="{{ route('panou.import.upload') }}" enctype="multipart/form-data" class="stack">@csrf
                <div class="field"><label class="field__label" for="fisier">Fișier CSV exportat din WooCommerce → Produse → Export</label><input id="fisier" type="file" name="fisier" accept=".csv,text/csv" required></div>
                <button class="btn btn--primary" type="submit">Încarcă și simulează</button>
            </form>
            <p class="small muted">Exportul inclus la instalare: {{ implode(', ', $bundled) ?: '—' }}.</p>
        </x-panou.panel>
        <x-panou.panel title="Imaginile produselor">
            <dl class="kv">
                <dt>Procesate</dt><dd>{{ $images['ready'] ?? 0 }}</dd>
                <dt>În așteptare</dt><dd>{{ $images['pending'] ?? 0 }} <span class="small muted">(cronul procesează ~15 pe minut)</span></dd>
                <dt>Eșuate</dt><dd>{{ $images['failed'] ?? 0 }}</dd>
            </dl>
            <div class="cluster" style="margin-top:1rem">
                <form method="post" action="{{ route('panou.import.images.process') }}">@csrf<button class="btn btn--small" type="submit">Procesează 10 acum</button></form>
                @if (($images['failed'] ?? 0) > 0)<form method="post" action="{{ route('panou.import.images.retry') }}">@csrf<button class="btn btn--small" type="submit">Reîncearcă imaginile eșuate</button></form>@endif
            </div>
            <p class="small muted">Imaginile se iau din wp-content/uploads al vechiului site (dacă e pe același cont) sau de pe optoplus.ro, cât timp vechiul site mai răspunde.</p>
        </x-panou.panel>
    </div>
    <x-panou.panel title="Rulări" :flush="true">
        <table class="table"><thead><tr><th>#</th><th>Fișier</th><th>Mod</th><th>Stare</th><th>Progres</th><th>Rezultate</th><th>Data</th></tr></thead>
            <tbody>@forelse ($runs as $r)<tr>
                <td><a class="row-link" href="{{ route('panou.import.show', $r->id) }}">{{ $r->id }}</a></td><td class="small">{{ $r->source_name }}</td>
                <td><x-panou.badge :tone="$r->mode === 'import' ? 'info' : null">{{ $r->mode }}</x-panou.badge></td><td>{{ $r->status }}</td><td>{{ $r->cursor }}/{{ $r->total }}</td>
                <td class="small">@foreach ((array) $r->counters as $k => $v){{ $k }}: {{ $v }} @endforeach</td><td class="small">{{ $r->created_at->format('d.m.Y H:i') }}</td>
            </tr>@empty<tr><td colspan="7" class="muted">Nicio rulare.</td></tr>@endforelse</tbody></table>
    </x-panou.panel>
@endsection
