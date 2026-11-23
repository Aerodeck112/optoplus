@extends('layouts.panou')
@section('title', $product->name)
@php($spec = $product->frameSpec)
@section('content')
    <x-panou.page-head :title="$product->name" :crumbs="['Produse' => route('panou.catalog.products'), $product->name => null]" :sub="$product->url()">
        <x-slot:actions>
            <a class="btn" href="{{ $product->url() }}" target="_blank" rel="noopener">Vezi pe site</a>
            @can('catalog.edit')<form method="post" action="{{ route('panou.catalog.product.duplicate', $product->id) }}" data-confirm="Creați o copie (ciornă) a acestui produs, cu aceleași date și poze?">@csrf<button class="btn" type="submit">Copiază rama</button></form>@endcan
            @can('catalog.publish')
                <form method="post" action="{{ route('panou.catalog.product.status', $product->id) }}" class="cluster">@csrf
                    @if ($product->status !== 'published')<button class="btn btn--primary" name="stare" value="published">Publică</button>@endif
                    @if ($product->status === 'published')<button class="btn" name="stare" value="draft">Trece în ciornă</button>@endif
                    @if ($product->status !== 'archived')<button class="btn btn--danger" name="stare" value="archived" title="Produsul dispare de pe site, dar rămâne în panou">Retrage</button>@endif
                </form>
                @if ($product->status !== 'published')
                    <form method="post" action="{{ route('panou.catalog.product.delete', $product->id) }}" data-confirm="Ștergeți definitiv „{{ $product->name }}”? Se șterg variantele, legăturile cu pozele și filtrele. Nu se poate anula.">@csrf<button class="btn btn--danger" type="submit">Șterge definitiv</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-panou.page-head>
    <div class="grid grid--aside">
        <div class="stack">
            <x-panou.panel title="Conținut">
                <form method="post" action="{{ route('panou.catalog.product.update', $product->id) }}" class="form-grid" id="produs">@csrf
                    <x-panou.field name="nume" label="Nume" :value="$product->name" required :full="true" />
                    <x-panou.field name="marca" type="select" label="Marcă" :options="$brands->all()" :value="$product->brand_id" placeholder="fără" />
                    <x-panou.field name="disponibilitate" type="select" label="Disponibilitate" :options="\App\Modules\Catalog\Models\Product::AVAILABILITY" :value="$product->availability_mode" :placeholder="false" hint="„Disponibilitate de confirmat” până primim datele de stoc reale." />
                    <x-panou.field name="zile_min" type="number" label="Pregătire: minim zile lucrătoare" :value="$product->lead_time_days_min" hint="Gol = nu se afișează niciun termen." />
                    <x-panou.field name="zile_max" type="number" label="Pregătire: maxim zile lucrătoare" :value="$product->lead_time_days_max" />
                    <div class="field field--full"><span class="field__label">Categorii</span>
                        <div class="cluster">@foreach ($categories as $cid => $path)<label class="check"><input type="checkbox" name="categorii[]" value="{{ $cid }}" @checked($product->categories->contains('id', $cid))><span>{{ $path }}</span></label>@endforeach</div></div>
                    <x-panou.field name="cu_lentile" type="checkbox" label="Se poate comanda cu lentile (configurator)" :value="$product->configurable_with_lenses" />
                    <x-panou.field name="scurta" type="textarea" label="Descriere scurtă (HTML simplu)" :value="$product->short_description" :full="true" />
                    <x-panou.field name="descriere" type="textarea" label="Descriere (HTML simplu: paragrafe, liste, titluri, linkuri)" :value="$product->description" :full="true" rows="10" />
                    @if ($product->isFrame())
                        <x-panou.field name="latime" type="number" label="Lățime lentilă (mm)" :value="$spec?->lens_width_mm" />
                        <x-panou.field name="punte" type="number" label="Punte (mm)" :value="$spec?->bridge_mm" />
                        <x-panou.field name="brat" type="number" label="Braț (mm)" :value="$spec?->temple_mm" />
                        <x-panou.field name="inaltime" type="number" label="Înălțime lentilă (mm)" :value="$spec?->lens_height_mm" />
                        <x-panou.field name="tip_rama" type="select" label="Tip ramă" :options="['full' => 'Ramă întreagă', 'semi_nylon' => 'Semi-ramă cu fir (nylon)', 'rimless_screw' => 'Fără contur (montaj prin găurire, șuruburi/capse)']" :value="$spec?->rim_type" placeholder="necunoscut" hint="Folosit de regulile de compatibilitate (ex. la montajul prin găurire nu se pot folosi lentile fotocromatice)." />
                    @endif
                    <div class="field field--full"><hr class="sep"><span class="field__label">Filtrele de pe site</span></div>
                    @include('catalog::panel.partials.attributes', ['attributes' => $attributes, 'selected' => array_map('intval', old('atribute', $product->attributeValues->pluck('id')->all()))])
                    <x-panou.field name="seo_titlu" label="Titlu SEO" :value="$product->seo_title" />
                    <x-panou.field name="seo_descriere" label="Descriere SEO" :value="$product->seo_description" />
                    <x-panou.field name="noindex" type="checkbox" label="Nu indexa (noindex)" :value="$product->noindex" />
                    <div class="form-actions">@can('catalog.edit')<button class="btn btn--primary" type="submit">Salvează</button>@endcan</div>
                </form>
                @if ($spec?->sources)<p class="small muted">Proveniența dimensiunilor: @foreach ($spec->sources as $k => $src){{ $k }}: {{ $src }}; @endforeach</p>@endif
            </x-panou.panel>
        </div>
        <div class="stack">
            <x-panou.panel title="Variante, prețuri și stoc">
                <form method="post" action="{{ route('panou.catalog.product.variants', $product->id) }}" class="stack">@csrf
                    @foreach ($product->variants as $v)
                        <fieldset class="stack" style="border:1px solid var(--line);border-radius:8px;padding:.6rem">
                            <legend class="small">{{ $v->sku }}@if($v->sku_generated) <x-panou.badge tone="warn">SKU generat</x-panou.badge>@endif</legend>
                            <x-panou.field :name="'v['.$v->id.'][nume]'" label="Nume (ex. culoarea)" :value="$v->name" />
                            <div class="cluster">
                                <x-panou.field :name="'v['.$v->id.'][pret]'" label="Preț cu TVA (lei)" :value="number_format($v->price / 100, 2, ',', '')" required />
                                <x-panou.field :name="'v['.$v->id.'][cost]'" label="Cost (lei)" :value="$v->cost !== null ? number_format($v->cost / 100, 2, ',', '') : ''" />
                                <x-panou.field :name="'v['.$v->id.'][stoc]'" type="number" label="Stoc" :value="$v->stock_qty" hint="gol = negestionat" />
                            </div>
                            <x-panou.field :name="'v['.$v->id.'][activa]'" type="checkbox" label="Activă" :value="$v->is_active" />
                        </fieldset>
                    @endforeach
                    @can('catalog.prices')<button class="btn btn--primary" type="submit">Salvează prețurile</button>@else<p class="small muted">Prețurile se modifică de cine are permisiunea „Prețuri”.</p>@endcan
                </form>
                <p class="small muted">Prețul afișat pe site vine din motorul de preț (preț + promoții active). Prețul barat apare doar cu o promoție datată.</p>
                @can('catalog.edit')
                <details class="perm-group" @if($errors->hasAny(['nume', 'sku', 'pret'])) open @endif>
                    <summary><strong>Adaugă variantă</strong> <span class="small muted">(altă culoare a aceleiași rame)</span></summary>
                    <form method="post" action="{{ route('panou.catalog.product.variant.add', $product->id) }}" class="stack" style="margin-top:.5rem">@csrf
                        <x-panou.field name="nume" label="Culoarea / varianta (ex. Havana)" required />
                        <x-panou.field name="sku" label="Cod produs (SKU)" hint="Gol = se generează automat." />
                        @can('catalog.prices')<x-panou.field name="pret" label="Preț cu TVA (lei)" required :value="$product->variants->first() ? number_format($product->variants->first()->price / 100, 2, ',', '') : ''" />@else<p class="small muted">Prețul îl completează cine are dreptul „Prețuri”.</p>@endcan
                        <x-panou.field name="stoc" type="number" label="Stoc" hint="Gol = negestionat." />
                        <button class="btn" type="submit">Adaugă varianta</button>
                    </form>
                </details>
                @endcan
            </x-panou.panel>
            <x-panou.panel title="Poze">
                @if ($product->images->isEmpty())<p class="muted">Produsul nu are încă poze.</p>@endif
                <div class="product-photos">
                @foreach ($product->images as $im)
                    <figure class="product-photo">
                        @if ($im->media)<img src="{{ $im->media->bestUrl(320) }}" alt="{{ $im->alt ?? $im->media->alt }}" loading="lazy">@else<span class="product-photo__missing small muted">poza nu este descărcată</span>@endif
                        <figcaption>
                            @if ($loop->first)<x-panou.badge tone="ok">principală</x-panou.badge>@endif
                            @can('catalog.edit')
                            <div class="product-photo__actions">
                                @unless ($loop->first)<form method="post" action="{{ route('panou.catalog.product.image', [$product->id, $im->id]) }}">@csrf<button class="btn btn--small" name="actiune" value="principala" title="Fă poza principală">★</button></form>
                                <form method="post" action="{{ route('panou.catalog.product.image', [$product->id, $im->id]) }}">@csrf<button class="btn btn--small" name="actiune" value="sus" title="Mută mai în față" aria-label="Mută mai în față">←</button></form>@endunless
                                @unless ($loop->last)<form method="post" action="{{ route('panou.catalog.product.image', [$product->id, $im->id]) }}">@csrf<button class="btn btn--small" name="actiune" value="jos" title="Mută mai în spate" aria-label="Mută mai în spate">→</button></form>@endunless
                                <form method="post" action="{{ route('panou.catalog.product.image', [$product->id, $im->id]) }}" data-confirm="Scoateți această poză de la produs?">@csrf<button class="btn btn--small btn--danger" name="actiune" value="sterge" title="Scoate poza" aria-label="Scoate poza">✕</button></form>
                            </div>
                            @if ($product->variants->count() > 1)
                                <form method="post" action="{{ route('panou.catalog.product.image', [$product->id, $im->id]) }}" class="product-photo__color" data-autosubmit>@csrf<input type="hidden" name="actiune" value="culoare">
                                    <label class="small" for="culoare-{{ $im->id }}">Culoarea</label>
                                    <select id="culoare-{{ $im->id }}" name="varianta">
                                        <option value="">Toate culorile</option>
                                        @foreach ($product->variants as $pv)<option value="{{ $pv->id }}" @selected((int) $im->variant_id === $pv->id)>{{ $pv->name ?: $pv->sku }}</option>@endforeach
                                    </select>
                                    <noscript><button class="btn btn--small">Salvează</button></noscript>
                                </form>
                            @endif
                            <details class="small"><summary>Text alternativ</summary>
                                <form method="post" action="{{ route('panou.catalog.product.image', [$product->id, $im->id]) }}" class="stack" style="gap:.3rem">@csrf<input type="hidden" name="actiune" value="alt"><input name="alt" value="{{ $im->alt }}" aria-label="Text alternativ" placeholder="ex. Ramă neagră, văzută din față"><button class="btn btn--small">Salvează</button></form>
                            </details>
                            @endcan
                        </figcaption>
                    </figure>
                @endforeach
                </div>
                <p class="small muted">★ = poza principală (apare în catalog) · ← → = ordinea în galerie.@if ($product->variants->count() > 1) · „Culoarea” = când clientul alege acea culoare, pe site apar doar pozele ei (prima devine imaginea mare); „Toate culorile” = pozele arătate la culorile care nu au poze proprii.@endif</p>
                @can('catalog.edit')
                <form method="post" action="{{ route('panou.catalog.product.images', $product->id) }}" enctype="multipart/form-data" class="stack" style="margin-top:.75rem">@csrf
                    <div class="field"><label class="field__label" for="poze">Adaugă poze (JPG, PNG, WebP; max. 10 MB; mai multe deodată)</label><input id="poze" type="file" name="poze[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
                    @if ($product->variants->count() > 1)
                        <div class="field"><label class="field__label" for="poze-culoare">Pentru culoarea</label>
                            <select id="poze-culoare" name="varianta"><option value="">Toate culorile</option>@foreach ($product->variants as $pv)<option value="{{ $pv->id }}">{{ $pv->name ?: $pv->sku }}</option>@endforeach</select>
                        </div>
                    @endif
                    @error('poze')<p class="field__error">{{ $message }}</p>@enderror @error('poze.*')<p class="field__error">{{ $message }}</p>@enderror
                    <button class="btn" type="submit">Încarcă pozele</button>
                </form>
                @endcan
            </x-panou.panel>
        </div>
    </div>
@endsection
