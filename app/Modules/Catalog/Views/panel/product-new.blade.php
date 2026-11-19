@extends('layouts.panou')
@section('title', 'Ramă nouă')
@section('content')
    <x-panou.page-head title="Ramă nouă" :crumbs="['Produse' => route('panou.catalog.products'), 'Ramă nouă' => null]"
        sub="Produsul se salvează ca ciornă: îl verificați, apoi apăsați „Publică” ca să apară pe site. Pentru un model asemănător cu unul existent, folosiți „Copiază rama” din pagina acelei rame." />
    <form method="post" action="{{ route('panou.catalog.product.store') }}" enctype="multipart/form-data" class="grid grid--aside">@csrf
        <div class="stack">
            <x-panou.panel title="Ce produs este">
                <div class="form-grid">
                    <x-panou.field name="tip" type="select" label="Tip" :options="['frame' => 'Ramă de ochelari', 'accessory' => 'Accesoriu (toc, lavetă, soluție…)']" value="frame" :placeholder="false" />
                    <x-panou.field name="nume" label="Nume (ex. Ray-Ban RX5154 Clubmaster)" required :full="true" />
                    <x-panou.field name="marca" type="select" label="Marcă" :options="$brands->all()" placeholder="fără marcă" hint="Marcă nouă: Catalog → Mărci." />
                    <x-panou.field name="disponibilitate" type="select" label="Disponibilitate" :options="\App\Modules\Catalog\Models\Product::AVAILABILITY" value="la_comanda" :placeholder="false" />
                    <x-panou.field name="cu_lentile" type="checkbox" label="Se poate comanda cu lentile (configurator)" :value="true" />
                    <div class="field field--full"><span class="field__label">Categorii</span>
                        <div class="cluster">@foreach ($categories as $cid => $path)<label class="check"><input type="checkbox" name="categorii[]" value="{{ $cid }}" @checked(in_array($cid, old('categorii', [$defaultCategory]))) ><span>{{ $path }}</span></label>@endforeach</div></div>
                </div>
            </x-panou.panel>
            <x-panou.panel title="Pentru cine și cum arată (filtrele de pe site)">
                <div class="form-grid">
                    @include('catalog::panel.partials.attributes', ['attributes' => $attributes, 'selected' => array_map('intval', old('atribute', []))])
                </div>
            </x-panou.panel>
            <x-panou.panel title="Dimensiuni (doar pentru rame)">
                <div class="form-grid">
                    <x-panou.field name="latime" type="number" label="Lățime lentilă (mm)" hint="Primul număr de pe braț, ex. 52 din 52□18 140." />
                    <x-panou.field name="punte" type="number" label="Punte (mm)" hint="Al doilea număr, ex. 18." />
                    <x-panou.field name="brat" type="number" label="Braț (mm)" hint="Al treilea număr, ex. 140." />
                    <x-panou.field name="inaltime" type="number" label="Înălțime lentilă (mm)" />
                    <x-panou.field name="tip_rama" type="select" label="Tip ramă" :options="['full' => 'Ramă întreagă', 'semi_nylon' => 'Semi-ramă cu fir (nylon)', 'rimless_screw' => 'Fără contur (montaj prin găurire)']" placeholder="necunoscut" hint="Folosit de regulile de compatibilitate (ex. găurire: fără lentile 1,50 / 1,56)." />
                </div>
            </x-panou.panel>
            <x-panou.panel title="Descriere">
                <div class="form-grid">
                    <x-panou.field name="scurta" type="textarea" label="Descriere scurtă (opțional)" :full="true" />
                    <x-panou.field name="descriere" type="textarea" label="Descriere (paragrafe, liste; HTML simplu)" :full="true" rows="8" />
                </div>
            </x-panou.panel>
        </div>
        <div class="stack">
            <x-panou.panel title="Prima variantă (culoare)">
                <div class="stack">
                    <x-panou.field name="varianta[nume]" label="Culoarea / varianta (ex. Negru mat)" />
                    <x-panou.field name="varianta[sku]" label="Cod produs (SKU)" hint="Gol = se generează automat." />
                    @if ($canPrice)
                        <x-panou.field name="varianta[pret]" label="Preț cu TVA (lei)" required />
                    @else
                        <p class="small muted">Prețul îl completează cine are dreptul „Prețuri” (până atunci produsul nu se poate publica).</p>
                    @endif
                    <x-panou.field name="varianta[stoc]" type="number" label="Stoc" hint="Gol = stocul nu se gestionează." />
                    <p class="small muted">Alte culori le adăugați după salvare, cu „Adaugă variantă”.</p>
                </div>
            </x-panou.panel>
            <x-panou.panel title="Poze">
                <div class="field">
                    <label class="field__label" for="poze">Poze (JPG, PNG, WebP; max. 10 MB fiecare; mai multe deodată)</label>
                    <input id="poze" type="file" name="poze[]" accept="image/jpeg,image/png,image/webp" multiple>
                    @error('poze.*')<p class="field__error">{{ $message }}</p>@enderror
                    <p class="small muted">Prima poză devine poza principală; ordinea se poate schimba după salvare. Siteul face automat variantele mici pentru telefon.</p>
                </div>
            </x-panou.panel>
            <div class="form-actions"><button class="btn btn--primary" type="submit">Salvează ca ciornă</button><a class="btn btn--ghost" href="{{ route('panou.catalog.products') }}">Renunță</a></div>
        </div>
    </form>
@endsection
