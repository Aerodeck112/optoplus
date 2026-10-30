@extends('layouts.site')
@section('title', $product->seo_title ?: $product->name)
@section('description', $product->seo_description ?: \App\Core\Support\Html::plain($product->lens->customer_summary.' Indici disponibili, intervale de fabricație, tratamente și prețuri pe '.($product->sale_unit === 'pair' ? 'pereche' : 'bucată').'.', 155))
@section('content')
@php($lens = $product->lens)
<div class="wrap">
    <x-site.breadcrumbs :items="['Lentile' => '/lentile/', $product->name => null]" />
    <div class="product">
        <div>
            <p>@if($product->made_in_house)<span class="tag tag--lab"><x-site.icon name="lab" :size="16" />Produsă în laboratorul Optoplus</span>@else<span class="tag">Producător: {{ $lens->supplier_name ?: 'terț' }}</span>@endif <span class="tag">{{ $lens->supplyFormLabel() }}</span></p>
            <h1>{{ $product->name }}</h1>
            <p class="lead">{{ $lens->customer_summary }}</p>
            @include('storefront::partials.family-diagram', ['code' => $lens->family->code])
            <p class="diagram-note">Schemă ilustrativă a zonelor de vedere ({{ mb_strtolower($lens->family->name) }}), nu la scară.</p>
            <div class="prose">
                <h2>Pentru cine este</h2>
                <p>{{ $lens->family->customer_description }}</p>
                {!! $product->description !!}
            </div>

            <h2 style="margin-top: 40px">Grosime (indice) și preț</h2>
            <p>Cu cât indicele e mai mare, cu atât lentila iese mai subțire și mai ușoară la aceeași rețetă. Prețul este pentru o {{ $product->sale_unit === 'pair' ? 'pereche' : 'bucată' }}, fără tratamente.</p>
            <div class="table-wrap box" style="padding: 8px 16px">
                <table class="table">
                    <thead><tr><th>Indice</th><th>Sferă (SPH)</th><th>Cilindru (CYL)</th>@if($lens->family->requires_add)<th>Adiție</th>@endif<th class="num">Preț</th></tr></thead>
                    <tbody>
                    @foreach ($offerings as $row)
                        @php($o = $row['offering'])
                        <tr>
                            <td><strong>{{ $o->material->indexLabel() }}</strong><br><small class="muted">{{ $o->material->name }}</small></td>
                            <td>{{ \App\Modules\Configurator\Support\EyeRx::format($o->sph_min) }} … {{ \App\Modules\Configurator\Support\EyeRx::format($o->sph_max) }}</td>
                            <td>până la {{ \App\Modules\Configurator\Support\EyeRx::format($o->cyl_min) }}</td>
                            @if($lens->family->requires_add)<td>{{ \App\Modules\Configurator\Support\EyeRx::format($o->add_min, false) }} … {{ \App\Modules\Configurator\Support\EyeRx::format($o->add_max, false) }}</td>@endif
                            <td class="num"><x-site.price :amount="$row['price']" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($unconfirmed)
                <p class="notice notice--warn"><x-site.icon name="info" /><span>Intervalele de fabricație sunt în curs de confirmare de către laborator. Fiecare comandă este verificată de un specialist înainte de producție; dacă rețeta nu se poate realiza, vă contactăm înainte de orice plată suplimentară.</span></p>
            @endif
            <p class="small muted">Cilindrul este exprimat în notația cu minus; rețetele scrise cu cilindru pozitiv sunt convertite automat, fără a schimba rețeta.</p>
        </div>
        <aside class="buy">
            <div class="buy__box">
                <div class="buy__price">@if($offerings->isNotEmpty())<span class="price__from">de la</span> <x-site.price :amount="$offerings->min('price')" /><small>/ {{ $product->sale_unit === 'pair' ? 'pereche' : 'bucată' }}</small>@endif</div>
                @if ($lens->quote_only)
                    <a class="btn btn--primary btn--block" href="/contact/?subiect={{ urlencode('Ofertă: '.$product->name) }}">Cere o ofertă</a>
                @else
                    <a class="btn btn--primary btn--block" href="/rame/">Alege rama, apoi lentilele</a>
                    <form method="post" action="/configurator/start/">@csrf<input type="hidden" name="intrare" value="fara_rama"><input type="hidden" name="lentila" value="{{ $product->id }}"><button class="btn btn--block" type="submit">Comandă doar lentilele</button></form>
                @endif
                <dl class="facts">
                    <dt>Unitate</dt><dd>{{ $product->sale_unit === 'pair' ? 'pereche (ambii ochi)' : 'bucată (un ochi)' }}</dd>
                    <dt>Disponibilitate</dt><dd>{{ $product->availabilityLabel() }}</dd>
                    @if ($product->lead_time_days_max)<dt>Producție</dt><dd>{{ $product->lead_time_days_min }}–{{ $product->lead_time_days_max }} zile lucrătoare, fără transport</dd>@endif
                    @if ($lens->required_measurements)<dt>Măsurători</dt><dd>{{ collect($lens->required_measurements)->map(fn ($m) => \App\Modules\Lenses\Models\LensProduct::MEASUREMENTS[$m] ?? $m)->implode(', ') }}</dd>@endif
                </dl>
            </div>
            @if ($treatments->isNotEmpty())
                <section class="box">
                    <h2>Tratamente disponibile</h2>
                    @foreach ($treatments as $t)
                        <details class="faq"><summary>{{ $t['treatment']->name }} · +{{ \App\Core\Support\Money::format($t['price_min']) }}<x-site.icon name="chevron-down" /></summary>
                            <div><p><strong>Ce face:</strong> {{ $t['treatment']->benefits }}</p><p><strong>Limite:</strong> {{ $t['treatment']->limits }}</p>
                            @if ($t['treatment']->brand_note)<p class="small muted">{{ $t['treatment']->brand_note }}</p>@endif
                            @if ($t['treatment']->datasheet)<p><a href="{{ $t['treatment']->datasheet->url() }}">Fișa tehnică (PDF)</a></p>@endif</div>
                        </details>
                    @endforeach
                </section>
            @endif
            @if ($faqs->isNotEmpty())
                <section class="faq">@foreach ($faqs as $faq)<details><summary>{{ $faq->question }}<x-site.icon name="chevron-down" /></summary><div>{!! $faq->answer !!}</div></details>@endforeach</section>
            @endif
        </aside>
    </div>
</div>
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'url' => url($product->url()), 'description' => $lens->customer_summary,
    'brand' => $product->made_in_house ? ['@type' => 'Brand', 'name' => 'Optoplus'] : null,
    'offers' => $offerings->isEmpty() ? null : ['@type' => 'AggregateOffer', 'priceCurrency' => 'RON', 'lowPrice' => number_format($offerings->min('price') / 100, 2, '.', ''), 'highPrice' => number_format($offerings->max('price') / 100, 2, '.', ''), 'offerCount' => $offerings->count()]]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@endsection
