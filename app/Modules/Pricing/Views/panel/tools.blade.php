@extends('layouts.panou')
@section('title', 'Gestionare prețuri')
@php($q = request()->query())
@php($money = fn ($b) => \App\Core\Support\Money::format((int) $b))
@section('content')
    <x-panou.page-head title="Gestionare prețuri" sub="Schimbați prețurile mai multor rame deodată (cu procent sau cu lei, în plus sau în minus, cu rotunjire) sau faceți o reducere pe o perioadă. Vedeți întâi lista cu prețurile noi; abia apoi aplicați. Orice modificare se poate anula din istoric.">
        <x-slot:actions>@can('pricing.view')<a class="btn" href="{{ route('panou.pricing.promotions.index') }}">Promoții și cupoane</a>@endcan</x-slot:actions>
    </x-panou.page-head>

    <form method="get" action="{{ route('panou.pricing.tools') }}" class="stack price-tools">
        <input type="hidden" name="previzualizare" value="1">
        <x-panou.panel title="1. Ce produse">
            <div class="form-grid">
                <x-panou.field name="tinta" type="select" label="Produsele" :options="\App\Modules\Pricing\Services\PriceTools::TARGETS" :value="$q['tinta'] ?? 'rame'" :placeholder="false" />
                <div class="field"><label class="field__label" for="f_marci">Mărcile (pentru „Anumite mărci”; Ctrl/⌘ pentru mai multe)</label>
                    <select id="f_marci" name="marci[]" multiple size="6">@foreach ($brands as $id => $n)<option value="{{ $id }}" @selected(in_array((string) $id, (array) ($q['marci'] ?? []), true))>{{ $n }}</option>@endforeach</select>
                    @error('marci')<span class="field__error">{{ $message }}</span>@enderror</div>
                <div class="field"><label class="field__label" for="f_categorii">Categoriile (pentru „Anumite categorii”)</label>
                    <select id="f_categorii" name="categorii[]" multiple size="6">@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected(in_array((string) $id, (array) ($q['categorii'] ?? []), true))>{{ $n }}</option>@endforeach</select>
                    @error('categorii')<span class="field__error">{{ $message }}</span>@enderror</div>
                <x-panou.field name="produse" type="textarea" label="Produsele (pentru „Anumite produse”): coduri SKU sau linkuri, câte unul pe rând" :value="$q['produse'] ?? ''" />
                <x-panou.field name="pret_min" label="Doar cu prețul de la (lei, opțional)" :value="$q['pret_min'] ?? ''" />
                <x-panou.field name="pret_max" label="până la (lei, opțional)" :value="$q['pret_max'] ?? ''" />
                <x-panou.field name="toate_starile" type="checkbox" label="Include și ramele nepublicate (ciorne, retrase)" :value="! empty($q['toate_starile'])" />
            </div>
        </x-panou.panel>

        <x-panou.panel title="2. Ce faceți">
            <fieldset class="mode-pick">
                <label class="check"><input type="radio" name="mod" value="baza" @checked(($q['mod'] ?? 'baza') === 'baza')><span><strong>Schimb prețul de bază</strong> (permanent: scumpire, ieftinire, preț nou)</span></label>
                @can('pricing.manage')<label class="check"><input type="radio" name="mod" value="reducere" @checked(($q['mod'] ?? '') === 'reducere')><span><strong>Fac o reducere pe o perioadă</strong> (prețul de bază rămâne; reducerea se aplică între două date)</span></label>@endcan
            </fieldset>
            <div class="grid-2-panels">
                <div class="mode-box">
                    <h3>Prețul de bază</h3>
                    <div class="form-grid">
                        <x-panou.field name="operatie" type="select" label="Operația" :options="\App\Modules\Pricing\Services\PriceTools::OPERATIONS" :value="$q['operatie'] ?? 'plus_proc'" :placeholder="false" />
                        <x-panou.field name="valoare" label="Valoarea (% sau lei, ex. 10 sau 19,90)" :value="$q['valoare'] ?? ''" />
                        <x-panou.field name="rotunjire" type="select" label="Rotunjirea prețului nou" :options="\App\Modules\Pricing\Services\PriceTools::ROUNDING" :value="$q['rotunjire'] ?? 'nu'" :placeholder="false" />
                    </div>
                </div>
                @can('pricing.manage')
                <div class="mode-box">
                    <h3>Reducerea</h3>
                    <div class="form-grid">
                        <x-panou.field name="tip_reducere" type="select" label="Tipul" :options="['percent' => 'Procent (%)', 'fixed_amount' => 'Sumă (lei)']" :value="$q['tip_reducere'] ?? 'percent'" :placeholder="false" />
                        <x-panou.field name="valoare_reducere" label="Valoarea (ex. 20 sau 50)" :value="$q['valoare_reducere'] ?? ''" />
                        <x-panou.field name="de_la" type="date" label="De la" :value="$q['de_la'] ?? now()->toDateString()" />
                        <x-panou.field name="pana_la" type="date" label="Până la" :value="$q['pana_la'] ?? ''" />
                        <x-panou.field name="eticheta" label="Numele afișat clienților (ex. Black Friday)" :value="$q['eticheta'] ?? ''" />
                    </div>
                    <p class="small muted">Pe site, prețul vechi tăiat apare doar când legea o permite: prețul anterior afișat este cel mai mic preț din ultimele 30 de zile înainte de reducere (Directiva Omnibus). Siteul ține istoricul prețurilor de la versiunea 1.0.27; până când istoricul acoperă 30 de zile, reducerea se aplică, dar se afișează doar prețul nou.</p>
                </div>
                @endcan
            </div>
        </x-panou.panel>
        <div class="form-actions"><button class="btn btn--primary" type="submit">Vezi prețurile noi</button></div>
    </form>

    @if ($preview)
        <x-panou.panel :title="'3. Previzualizare: '.$preview['total'].' variante ('.$preview['products'].' produse)'">
            @if ($preview['total'] === 0)
                <x-panou.empty title="Niciun produs în selecție">Schimbați filtrele de mai sus.</x-panou.empty>
            @else
                <p><strong>{{ $params['mod'] === 'baza' ? $tools->describe($params) : 'Reducere '.$params['valoare_reducere'].($params['tip_reducere'] === 'percent' ? '%' : ' lei').' între '.\Illuminate\Support\Carbon::parse($params['de_la'])->format('d.m.Y').' și '.\Illuminate\Support\Carbon::parse($params['pana_la'])->format('d.m.Y') }}</strong></p>
                @if ($preview['below_cost'])<div class="alert alert--warn">Atenție: {{ $preview['below_cost'] }} variante ar ajunge sub costul de achiziție completat în panou.</div>@endif
                @if ($preview['skipped'])<div class="alert alert--warn">{{ $preview['skipped'] }} variante ar ajunge la 0 lei sau mai puțin și nu se modifică.</div>@endif
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Produs</th><th>Cod</th><th class="num">Preț acum</th><th class="num">{{ $params['mod'] === 'baza' ? 'Preț nou' : 'Cu reducere (estimat)' }}</th><th class="num">Diferență</th></tr></thead>
                    <tbody>@foreach ($preview['rows'] as $row)
                        <tr @if($row['below_cost']) class="row--warn" @endif>
                            <td><a href="{{ route('panou.catalog.product', $row['variant']->product_id) }}">{{ $row['variant']->product->name }}</a>@if($row['variant']->name) <span class="muted">· {{ $row['variant']->name }}</span>@endif</td>
                            <td class="small">{{ $row['variant']->sku }}</td>
                            <td class="num">{{ $money($row['old']) }}</td>
                            <td class="num"><strong>{{ $money($row['new']) }}</strong></td>
                            <td class="num {{ $row['new'] > $row['old'] ? 'up' : 'down' }}">{{ $row['new'] > $row['old'] ? '+' : '−' }}{{ $money(abs($row['new'] - $row['old'])) }}</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                @if ($preview['total'] > count($preview['rows']))<p class="small muted">Se afișează primele {{ count($preview['rows']) }} din {{ $preview['total'] }}; se aplică tuturor.</p>@endif
                <form method="post" action="{{ route('panou.pricing.tools.apply') }}" class="cluster" style="margin-top:1rem"
                      data-confirm="{{ $params['mod'] === 'baza' ? 'Modificați prețul pentru '.$preview['total'].' variante? Se poate anula din istoric.' : 'Porniți reducerea pentru '.$preview['products'].' produse?' }}">@csrf
                    @foreach (\Illuminate\Support\Arr::except($q, ['previzualizare']) as $k => $v)
                        @if (is_array($v))
                            @foreach ($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
                        @else
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <button class="btn btn--primary" type="submit">{{ $params['mod'] === 'baza' ? 'Aplică prețurile noi' : 'Pornește reducerea' }}</button>
                    <a class="btn" href="{{ route('panou.pricing.tools') }}">Renunță</a>
                </form>
            @endif
        </x-panou.panel>
    @endif

    <x-panou.panel title="Istoricul modificărilor de preț">
        @if ($batches->isEmpty())
            <p class="muted">Încă nicio modificare în masă.</p>
        @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Data</th><th>Modificarea</th><th class="num">Variante</th><th>De către</th><th></th></tr></thead>
            <tbody>@foreach ($batches as $b)
                <tr>
                    <td class="num">{{ \Illuminate\Support\Carbon::parse($b->created_at)->format('d.m.Y H:i') }}</td>
                    <td>{{ $b->description }}</td>
                    <td class="num">{{ $b->items_count }}</td>
                    <td>{{ $b->user_name }}</td>
                    <td>@if ($b->reverted_at)<x-panou.badge>anulată {{ \Illuminate\Support\Carbon::parse($b->reverted_at)->format('d.m.Y') }}</x-panou.badge>@elseif ($b->items_count)
                        <form method="post" action="{{ route('panou.pricing.tools.revert', $b->id) }}" data-confirm="Readuceți prețurile de dinainte de această modificare?">@csrf<button class="btn btn--small">Anulează</button></form>@endif</td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @endif
        @if ($discounts->isNotEmpty())
            <h3 class="h-reset" style="margin-top:1rem">Reduceri create de aici</h3>
            <ul>@foreach ($discounts as $d)<li><a href="{{ route('panou.pricing.promotions.edit', $d->id) }}">{{ $d->name }}</a> — {{ $d->is_active ? 'activă' : 'oprită' }}@if ($d->ends_at && $d->ends_at->isPast()) (încheiată)@endif</li>@endforeach</ul>
        @endif
    </x-panou.panel>
@endsection
