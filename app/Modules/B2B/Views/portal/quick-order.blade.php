@extends('b2b::portal.shell')
@section('title', 'Comandă rapidă')
@section('heading', 'Comandă rapidă')
@section('body')
<p>Un rând = o lucrare. Folosiți <strong>codul intern al pacientului</strong> (nu numele). Valorile se scriu ca pe rețetă (ex. −2,25); cilindrul se acceptă în notație plus sau minus. Toate rândurile se verifică pe server cu matricea de fabricație; dacă un rând are o problemă, nu se adaugă nimic până nu îl corectați.</p>
<form method="post" action="/portal/comanda-rapida/" class="box" data-module="portal">
    @csrf
    <datalist id="oferte">@foreach ($offerings as $o)<option value="{{ $o->code }}">{{ $o->product->name }} {{ $o->material->indexLabel() }}</option>@endforeach</datalist>
    <div class="table-wrap">
    <table class="table qo">
        <thead><tr><th>Cod pacient</th><th>Lentila (cod)</th><th>Ochi</th><th>OD SPH</th><th>OD CYL</th><th>OD AX</th><th>OD ADD</th><th>OS SPH</th><th>OS CYL</th><th>OS AX</th><th>OS ADD</th><th>DP</th><th>Tratamente</th><th>Buc.</th></tr></thead>
        <tbody>
        @for ($i = 0; $i < $rows; $i++)
            @php($o = fn ($k) => old("r.$i.$k"))
            <tr @error("r.$i") class="is-invalid" @enderror>
                <td><input name="r[{{ $i }}][cod_pacient]" value="{{ $o('cod_pacient') }}" maxlength="60" aria-label="Cod pacient, rândul {{ $i + 1 }}"></td>
                <td><input name="r[{{ $i }}][lentila]" value="{{ $o('lentila') }}" list="oferte" aria-label="Lentila, rândul {{ $i + 1 }}" class="qo-wide"></td>
                <td><select name="r[{{ $i }}][ochi]" aria-label="Ochi">@foreach (['ambii' => 'ambii', 'od' => 'OD', 'os' => 'OS'] as $v => $l)<option value="{{ $v }}" @selected($o('ochi') === $v)>{{ $l }}</option>@endforeach</select></td>
                @foreach (['od_sph', 'od_cyl', 'od_ax', 'od_add', 'os_sph', 'os_cyl', 'os_ax', 'os_add', 'dp'] as $f)
                    <td><input name="r[{{ $i }}][{{ $f }}]" value="{{ $o($f) }}" inputmode="decimal" autocomplete="off" aria-label="{{ strtoupper(str_replace('_', ' ', $f)) }}, rândul {{ $i + 1 }}"></td>
                @endforeach
                <td><input name="r[{{ $i }}][tratamente]" value="{{ $o('tratamente') }}" aria-label="Tratamente" placeholder="cod;cod"></td>
                <td><input name="r[{{ $i }}][cantitate]" value="{{ $o('cantitate') ?? 1 }}" inputmode="numeric" aria-label="Bucăți" class="qo-narrow"></td>
            </tr>
        @endfor
        </tbody>
    </table>
    </div>
    <div class="cfg__nav">
        <button class="btn btn--ghost" type="button" data-add-row>Adaugă un rând</button>
        <button class="btn btn--primary" type="submit">Verifică și adaugă în coș</button>
    </div>
</form>
<details class="faq"><summary>Codurile lentilelor și tratamentelor<x-site.icon name="chevron-down" /></summary>
    <div class="table-wrap"><table class="table"><thead><tr><th>Cod</th><th>Lentila</th></tr></thead><tbody>
        @foreach ($offerings as $o)<tr><td><code>{{ $o->code }}</code></td><td>{{ $o->product->name }}, indice {{ $o->material->indexLabel() }}@if($o->product->made_in_house) <span class="tag tag--lab">produsă de noi</span>@endif</td></tr>@endforeach
    </tbody></table></div>
    <p class="small">Tratamente: <code>antireflex-hidrofob</code>, <code>filtru-lumina-albastra</code>, <code>fotocromatic:gri_85</code> (cu opțiunea după „:”). Se separă cu „;”.</p>
</details>
@endsection
