@extends('layouts.panou')
@section('title', 'Rularea #'.$run->id)
@section('content')
    <x-panou.page-head :title="'Rularea #'.$run->id.' — '.($run->mode === 'simulare' ? 'simulare' : 'import')" :crumbs="['Import' => route('panou.import.index'), '#'.$run->id => null]" :sub="$run->source_name.' · '.$run->status.' · '.$run->cursor.'/'.$run->total.' produse'" />
    <div class="metrics">@foreach ((array) $run->counters as $k => $v)<div class="metric"><span class="metric__label">{{ $k }}</span><span class="metric__value">{{ $v }}</span></div>@endforeach</div>
    @if ($run->mode === 'simulare' && $run->status === 'finalizat')
        <x-panou.panel title="Importul efectiv">
            <p>Simularea arată ce s-ar schimba. Importul actualizează produsele existente (după ID-ul WooCommerce), creează produsele noi, păstrează URL-urile și adaugă redirecturi pentru adresele schimbate. Comenzile, conturile și conținutul paginilor nu sunt atinse.</p>
            <div class="cluster">
                <form method="post" action="{{ route('panou.import.commit') }}">@csrf<input type="hidden" name="fisier" value="{{ $run->source_name }}"><button class="btn btn--primary" type="submit">Importă acum</button></form>
                <form method="post" action="{{ route('panou.import.reconcile') }}">@csrf<input type="hidden" name="fisier" value="{{ $run->source_name }}"><button class="btn" type="submit">Reconciliere cu catalogul actual</button></form>
            </div>
        </x-panou.panel>
    @endif
    <x-panou.panel title="Produse cu erori sau avertismente" :flush="true">
        <table class="table small"><thead><tr><th>ID WooCommerce</th><th>Produs</th><th>Acțiune</th><th>Mesaje</th></tr></thead>
            <tbody>@forelse (collect($run->report['produse'] ?? [])->filter(fn ($p) => ! empty($p['errors']) || ! empty($p['warnings']))->take(300) as $id => $p)
                <tr><td>{{ $id }}</td><td>{{ $p['nume'] ?? '' }}</td><td>{{ $p['status'] ?? $p['actiune'] ?? '' }}</td><td>{{ implode(' ', array_merge($p['errors'] ?? [], $p['warnings'] ?? [])) }}</td></tr>
            @empty<tr><td colspan="4" class="muted">Fără erori sau avertismente.</td></tr>@endforelse</tbody></table>
    </x-panou.panel>
@endsection
