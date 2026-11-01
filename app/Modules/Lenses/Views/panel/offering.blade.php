@extends('layouts.panou')
@section('title', $o->code)
@php($f = fn ($v) => $v === null ? '' : \App\Modules\Configurator\Support\EyeRx::format($v))
@php($lei = fn ($v) => $v === null ? '' : number_format($v / 100, 2, ',', ''))
@section('content')
    <x-panou.page-head :title="$o->product->name.' '.$o->material->indexLabel()" :crumbs="['Lentile' => route('panou.lenses.index'), $o->code => null]" :sub="'Cod '.$o->code.' · sursa: '.($o->source ?? '—')" />
    <form method="post" action="{{ route('panou.lenses.offering.update', $o->id) }}">@csrf
    <div class="grid grid--aside">
        <x-panou.panel title="Intervale de fabricație (dioptrii, ca pe rețetă)">
            <div class="form-grid form-grid--3">
                <x-panou.field name="sph_min" label="SPH minim" :value="$f($o->sph_min)" required />
                <x-panou.field name="sph_max" label="SPH maxim" :value="$f($o->sph_max)" required />
                <x-panou.field name="max_combined" label="|SPH| + |CYL| maxim" :value="$f($o->max_combined)" hint="gol = fără limită combinată" />
                <x-panou.field name="cyl_min" label="CYL minim (notație minus)" :value="$f($o->cyl_min)" required />
                <x-panou.field name="cyl_max" label="CYL maxim" :value="$f($o->cyl_max)" required hint="de regulă 0" />
                <x-panou.field name="diametru" type="number" label="Diametru (mm)" :value="$o->diameter_mm" />
                <x-panou.field name="add_min" label="ADD minim" :value="$f($o->add_min)" hint="doar progresive/degresive/bifocale" />
                <x-panou.field name="add_max" label="ADD maxim" :value="$f($o->add_max)" />
            </div>
            <p class="small muted">Rețetele cu cilindru plus se transpun automat în notație minus înainte de comparare.</p>
        </x-panou.panel>
        <x-panou.panel title="Preț și stare">
            <div class="stack">
                <x-panou.field name="pret" label="Preț B2C cu TVA (lei, per {{ $o->product->sale_unit === 'pair' ? 'pereche' : 'bucată' }})" :value="$lei($o->price)" required />
                <x-panou.field name="cost" label="Cost real (lei)" :value="$lei($o->cost)" />
                <div class="cluster"><x-panou.field name="zile_min" type="number" label="Producție min. (zile)" :value="$o->lead_time_days_min" /><x-panou.field name="zile_max" type="number" label="max." :value="$o->lead_time_days_max" /></div>
                <x-panou.field name="stare" type="select" label="Stare" :options="['activ' => 'Activă (confirmată de laborator)', 'neconfirmat' => 'Neconfirmată', 'inactiv' => 'Inactivă (nu se vinde)']" :value="$o->status" :placeholder="false" />
                <x-panou.field name="verificare" type="checkbox" label="Verificare de specialist înainte de producție" :value="$o->requires_review" />
                <x-panou.field name="sursa" label="Sursa datelor" :value="$o->source" hint="ex. „fișa tehnică laborator, 11.2026”" />
            </div>
        </x-panou.panel>
    </div>
    <x-panou.panel title="Tratamente disponibile pentru această ofertă" :flush="true">
        <table class="table"><thead><tr><th>Tratament</th><th>Preț (lei)</th><th>Inclus</th><th>Stare</th></tr></thead>
            <tbody>@foreach ($o->treatments as $t)<tr>
                <td>{{ $t->treatment->name }}@if($t->option_codes)<br><span class="small muted">opțiuni: {{ implode(', ', $t->option_codes) }}</span>@endif</td>
                <td><x-panou.field :name="'t['.$t->id.'][pret]'" label="Preț" :value="$lei($t->price)" /></td>
                <td><x-panou.field :name="'t['.$t->id.'][inclus]'" type="checkbox" label="inclus" :value="$t->is_included" /></td>
                <td><x-panou.field :name="'t['.$t->id.'][stare]'" type="select" label="Stare" :options="['activ' => 'activ', 'neconfirmat' => 'neconfirmat', 'inactiv' => 'inactiv']" :value="$t->status" :placeholder="false" /></td>
            </tr>@endforeach</tbody></table>
        <x-slot:footer>@can('lenses.edit')<button class="btn btn--primary" type="submit">Salvează oferta</button>@endcan <span class="small muted">Comenzile deja plasate nu se modifică (au snapshot).</span></x-slot:footer>
    </x-panou.panel>
    </form>
@endsection
