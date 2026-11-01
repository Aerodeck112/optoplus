@extends('layouts.panou')
@section('title', 'Adrese negăsite')
@section('content')
    <x-panou.page-head title="Adrese negăsite (404)" sub="Adresele cerute de vizitatori sau motoare de căutare care nu există. Pentru cele importante, adăugați un redirect spre pagina cea mai apropiată." />
    <x-panou.panel :flush="true">
        <table class="table"><thead><tr><th>Adresa</th><th>Accesări</th><th>Ultima dată</th><th>Venit de la</th></tr></thead>
            <tbody>@forelse ($rows as $r)<tr><td><code>{{ $r->path }}</code></td><td>{{ $r->hits }}</td><td>{{ \Illuminate\Support\Carbon::parse($r->last_seen_at)->format('d.m.Y H:i') }}</td><td class="small">{{ $r->last_referer }}</td></tr>@empty<tr><td colspan="4" class="muted">Nicio adresă negăsită înregistrată.</td></tr>@endforelse</tbody></table>
    </x-panou.panel>
@endsection
