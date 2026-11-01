@extends('layouts.panou')
@section('title', 'Lentile și matrice')
@php($f = fn ($v) => $v === null ? '—' : \App\Modules\Configurator\Support\EyeRx::format($v))
@section('content')
    <x-panou.page-head title="Lentile și matricea de fabricație" sub="Ce se poate produce, în ce intervale și la ce preț. Configuratorul blochează tot ce iese din matrice." />
    @if ($unconfirmed)
        <div class="alert alert--warn">{{ $unconfirmed }} oferte sunt „neconfirmate” (preluate din configuratorul vechi). {{ $sellUnconfirmed ? 'Se pot vinde momentan (OPTOPLUS_SELL_UNCONFIRMED_LENSES=true), cu verificare de specialist la fiecare comandă.' : 'Nu se vând până la confirmare.' }} Laboratorul trebuie să confirme intervalele și prețurile.</div>
    @endif
    @foreach ($groups as $offerings)
        @php($first = $offerings->first())
        <x-panou.panel :title="$first->product->name.' · '.($first->lensProduct?->family?->name ?? '')" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Cod</th><th>Indice</th><th>SPH</th><th>CYL</th><th>ADD</th><th>|SPH|+|CYL|</th><th class="num">Preț</th><th>Tratamente</th><th>Stare</th></tr></thead>
                <tbody>@foreach ($offerings as $o)<tr>
                    <td><a class="row-link" href="{{ route('panou.lenses.offering', $o->id) }}">{{ $o->code }}</a></td>
                    <td>{{ $o->material->indexLabel() }}</td>
                    <td>{{ $f($o->sph_min) }} … {{ $f($o->sph_max) }}</td>
                    <td>{{ $f($o->cyl_min) }} … {{ $f($o->cyl_max) }}</td>
                    <td>@if($o->add_min !== null){{ $f($o->add_min) }} … {{ $f($o->add_max) }}@else — @endif</td>
                    <td>{{ $o->max_combined !== null ? $f($o->max_combined) : '—' }}</td>
                    <td class="num">{{ \App\Core\Support\Money::format($o->price) }}</td>
                    <td class="small">{{ $o->treatments->map(fn ($t) => $t->treatment->name)->implode(', ') ?: '—' }}</td>
                    <td><x-panou.badge :tone="['activ' => 'ok', 'neconfirmat' => 'warn', 'inactiv' => 'danger'][$o->status] ?? null">{{ $o->status }}</x-panou.badge></td>
                </tr>@endforeach</tbody>
            </table></div>
            <x-slot:footer><span class="small muted">{{ $first->product->made_in_house ? 'Produsă în laboratorul Optoplus' : 'Produs comercializat' }} · unitate: {{ $first->product->sale_unit === 'pair' ? 'pereche' : 'bucată' }} · formă: {{ $first->lensProduct?->supplyFormLabel() }}</span></x-slot:footer>
        </x-panou.panel>
    @endforeach
@endsection
