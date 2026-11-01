@extends('layouts.panou')
@section('title', $product->name)
@php($spec = $product->frameSpec)
@section('content')
    <x-panou.page-head :title="$product->name" :crumbs="['Produse' => route('panou.catalog.products'), $product->name => null]" :sub="$product->url()">
        <x-slot:actions>
            <a class="btn" href="{{ $product->url() }}" target="_blank" rel="noopener">Vezi pe site</a>
            @can('catalog.publish')
                <form method="post" action="{{ route('panou.catalog.product.status', $product->id) }}" class="cluster">@csrf
                    @if ($product->status !== 'published')<button class="btn btn--primary" name="stare" value="published">Publică</button>@endif
                    @if ($product->status === 'published')<button class="btn" name="stare" value="draft">Trece în ciornă</button>@endif
                    @if ($product->status !== 'archived')<button class="btn btn--danger" name="stare" value="archived">Retrage</button>@endif
                </form>
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
                        <x-panou.field name="tip_rama" type="select" label="Tip ramă" :options="['full' => 'Ramă întreagă', 'semi_nylon' => 'Semi-ramă cu fir (nylon)', 'rimless_screw' => 'Fără ramă, cu capse']" :value="$spec?->rim_type" placeholder="necunoscut" hint="Folosit de regulile de compatibilitate (ex. indice minim pentru fir/capse)." />
                    @endif
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
            </x-panou.panel>
            <x-panou.panel title="Imagini">
                <div class="cluster">@foreach ($product->images as $im)@if($im->media)<img src="{{ $im->media->bestUrl(320) }}" alt="{{ $im->media->alt }}" width="96" height="80" style="object-fit:contain;border:1px solid var(--line);border-radius:6px">@endif @endforeach</div>
                <p class="small muted">{{ $product->images->count() }} imagini · biblioteca media: „Site → Media”.</p>
            </x-panou.panel>
        </div>
    </div>
@endsection
