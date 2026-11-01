@extends('layouts.panou')
@section('title', 'Reconciliere')
@section('content')
    <x-panou.page-head :title="'Reconciliere: '.$file" :crumbs="['Import' => route('panou.import.index'), 'Reconciliere' => null]" sub="Compară exportul cu catalogul actual: produse, variante, imagini, mărci, prețuri, URL-uri." />
    <x-panou.panel :flush="true">
        <table class="table"><thead><tr><th>Verificare</th><th>Rezultat</th></tr></thead><tbody>
            @foreach ($checks as $k => $c)
                <tr><td>{{ str_replace('_', ' ', $k) }}</td><td>@if(isset($c['ok']))<x-panou.badge :tone="$c['ok'] ? 'ok' : 'danger'">{{ $c['ok'] ? 'OK' : 'diferențe' }}</x-panou.badge>@endif
                    <span class="small">{{ collect($c)->except(['ok', 'diferente'])->map(fn ($v, $kk) => $kk.': '.(is_scalar($v) ? $v : json_encode($v)))->implode(' · ') }}</span>
                    @if (! empty($c['diferente']))<details><summary class="small">{{ count($c['diferente']) }} diferențe</summary><pre class="small">{{ json_encode(array_slice($c['diferente'], 0, 50), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</td></tr>
            @endforeach
        </tbody></table>
    </x-panou.panel>
@endsection
