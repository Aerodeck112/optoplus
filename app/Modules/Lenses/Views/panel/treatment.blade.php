@extends('layouts.panou')
@section('title', $t->exists ? $t->name : 'Tratament nou')
@section('content')
    <x-panou.page-head :title="$t->exists ? $t->name : 'Tratament nou'" :crumbs="['Tratamente lentile' => route('panou.lenses.treatments.index'), ($t->exists ? 'Editare' : 'Nou') => null]"
        :sub="$t->exists ? 'Cod intern: '.$t->code.' · tip: '.($kinds[$t->kind] ?? $t->kind) : 'După creare, adăugați tratamentul la lentilele potrivite, cu prețul lui.'" />
    <form method="post" action="{{ $t->exists ? route('panou.lenses.treatments.update', $t->id) : route('panou.lenses.treatments.store') }}">
        @csrf
        <div class="grid grid--aside">
            <x-panou.panel title="Ce vede clientul">
                <div class="form-grid">
                    <x-panou.field name="nume" label="Numele tratamentului" :value="$t->name" required />
                    @unless ($t->exists)
                        <x-panou.field name="tip" type="select" label="Tipul (pentru ilustrația din configurator)" :options="$kinds" :value="$t->kind" :placeholder="false" />
                    @endunless
                    <x-panou.field name="ce_face" type="textarea" label="Ce face (în cuvinte simple)" :value="$t->benefits" full required />
                    <x-panou.field name="limite" type="textarea" label="Limite (ce nu face; fără promisiuni medicale)" :value="$t->limits" full required />
                </div>
            </x-panou.panel>
            <x-panou.panel title="Stare">
                <div class="stack">
                    <x-panou.field name="activ" type="checkbox" label="Activ (apare în configurator, la lentilele unde e adăugat)" :value="$t->is_active" />
                    <x-panou.field name="cu_nuanta" type="checkbox" label="Clientul alege o nuanță (ex. fotocromatic)" :value="$t->requires_option" />
                    <x-panou.field name="ordine" type="number" label="Ordinea în listă" :value="$t->sort" />
                </div>
                @if ($usedIn->isNotEmpty())
                    <hr class="sep">
                    <p class="small"><strong>Disponibil pe {{ $usedIn->count() }} lentile</strong> (prețul se schimbă la fiecare lentilă):</p>
                    <ul class="small">@foreach ($usedIn as $u)<li><a href="{{ route('panou.lenses.offering', $u->offering_id) }}">{{ $u->offering?->product?->name }} {{ $u->offering?->material?->name }}</a> — {{ $u->is_included ? 'inclus' : \App\Core\Support\Money::format($u->price) }}@if($u->status === 'inactiv') <span class="muted">(inactiv)</span>@endif</li>@endforeach</ul>
                @endif
            </x-panou.panel>
        </div>
        <x-panou.panel title="Nuanțe" :flush="true">
            <p class="small muted" style="padding: 0 1rem">Nuanțele se folosesc doar dacă e bifat „Clientul alege o nuanță”. La fiecare lentilă alegeți apoi care nuanțe sunt permise (ex. la indicele 1.60 doar 85%).</p>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Nuanță</th><th>Culoare (pentru buline)</th><th>Activă</th><th>Cod</th></tr></thead>
                <tbody>
                @foreach ($t->options as $op)
                    <tr>
                        <td><x-panou.field :name="'nuante['.$op->id.'][eticheta]'" label="Eticheta" :value="$op->label" /></td>
                        <td><x-panou.field :name="'nuante['.$op->id.'][culoare]'" label="#RRGGBB" :value="$op->swatch" /></td>
                        <td><x-panou.field :name="'nuante['.$op->id.'][activ]'" type="checkbox" label="activă" :value="$op->is_active" /></td>
                        <td class="small muted">{{ $op->code }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><x-panou.field name="nuanta_noua[eticheta]" label="Nuanță nouă (ex. Verde 85%)" value="" /></td>
                    <td><x-panou.field name="nuanta_noua[culoare]" label="#RRGGBB" value="" /></td>
                    <td colspan="2" class="small muted">Se adaugă activă la tratament; la lentile o bifați unde e posibilă.</td>
                </tr>
                </tbody>
            </table></div>
            <x-slot:footer>@can('lenses.edit')<button class="btn btn--primary" type="submit">{{ $t->exists ? 'Salvează tratamentul' : 'Creează tratamentul' }}</button>@endcan <span class="small muted">Comenzile deja plasate nu se modifică (au snapshot).</span></x-slot:footer>
        </x-panou.panel>
    </form>
@endsection
