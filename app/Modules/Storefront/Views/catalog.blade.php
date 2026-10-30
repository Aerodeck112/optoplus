@extends('layouts.site')
@section('title', $heading.($products->currentPage() > 1 ? ' — pagina '.$products->currentPage() : ''))
@section('description', $intro ? \Illuminate\Support\Str::limit($intro, 155) : $heading.' din catalogul Optoplus. Comandă rama simplă sau cu lentile produse în laboratorul nostru.')
@section('canonical', $canonical)
@if ($noindex)@section('robots', 'noindex, follow')@endif
@section('content')
<div class="wrap" data-module="filters">
    <x-site.breadcrumbs :items="$crumbs" />
    <header class="page-head">
        <h1>{{ $heading }}</h1>
        @if ($intro)<p class="lead">{{ $intro }}</p>@endif
        <p class="muted small">Ramele sunt produse de mărcile afișate; Optoplus produce lentilele pe care le montăm în ele.</p>
    </header>
    <div class="catalog">
        <aside aria-label="Filtre">
            <button class="btn btn--block filters-toggle" type="button" data-filters-toggle aria-expanded="false" aria-controls="filtre">Filtrează și ordonează</button>
            <div class="filters-panel" id="filtre" data-filters-panel hidden>
                <form class="filters" method="get" action="{{ $basePath }}" data-autosubmit>
                    @if ($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    @foreach ($facets as $code => $facet)
                        @php($param = $code === 'brand' ? 'marca' : $code)
                        @php($locked = $code === 'brand' ? ($preset['brands'] ?? []) : ($preset['attributes'][$code] ?? []))
                        <details @if(collect($facet['values'])->contains('checked', true) || $loop->index < 2) open @endif>
                            <summary>{{ $facet['label'] }}<x-site.icon name="chevron-down" :size="18" /></summary>
                            @foreach ($facet['values'] as $v)
                                <label>
                                    <input type="checkbox" name="{{ $param }}[]" value="{{ $v['slug'] }}" @checked($v['checked']) @disabled(in_array($v['slug'], $locked, true))>
                                    {{ $v['label'] }}<span class="count">{{ $v['count'] }}</span>
                                </label>
                            @endforeach
                        </details>
                    @endforeach
                    <details @if($filters['price_min'] || $filters['price_max']) open @endif>
                        <summary>Preț ramă (lei)<x-site.icon name="chevron-down" :size="18" /></summary>
                        <div class="filters__range">
                            <label class="sr-only" for="pret_min">Preț minim</label><input id="pret_min" type="number" name="pret_min" min="0" inputmode="numeric" placeholder="min" value="{{ $filters['price_min'] ? $filters['price_min'] / 100 : '' }}">
                            <span>—</span>
                            <label class="sr-only" for="pret_max">Preț maxim</label><input id="pret_max" type="number" name="pret_max" min="0" inputmode="numeric" placeholder="max" value="{{ $filters['price_max'] ? $filters['price_max'] / 100 : '' }}">
                        </div>
                    </details>
                    @if ($widthRange[1] > 0)
                        <details @if($filters['width_min'] || $filters['width_max']) open @endif>
                            <summary>Lățimea lentilei (mm)<x-site.icon name="chevron-down" :size="18" /></summary>
                            <p class="small muted" style="margin: 8px 0 0">Prima cifră gravată pe brațul ochelarilor tăi actuali (ex. <strong>52</strong>-18-140).</p>
                            <div class="filters__range">
                                <label class="sr-only" for="latime_min">Lățime minimă</label><input id="latime_min" type="number" name="latime_min" min="{{ $widthRange[0] }}" max="{{ $widthRange[1] }}" placeholder="{{ $widthRange[0] }}" value="{{ $filters['width_min'] }}">
                                <span>—</span>
                                <label class="sr-only" for="latime_max">Lățime maximă</label><input id="latime_max" type="number" name="latime_max" min="{{ $widthRange[0] }}" max="{{ $widthRange[1] }}" placeholder="{{ $widthRange[1] }}" value="{{ $filters['width_max'] }}">
                            </div>
                        </details>
                    @endif
                    <div class="field" style="margin-top: 12px">
                        <label for="ordine">Ordonează</label>
                        <select id="ordine" name="ordine">@foreach ($sorts as $k => $label)<option value="{{ $k }}" @selected($filters['sort'] === $k)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <button class="btn btn--primary btn--block" type="submit" style="margin-top: 12px">Aplică filtrele</button>
                    @if (request()->query())<a class="btn btn--ghost btn--block" href="{{ $basePath }}">Șterge filtrele</a>@endif
                </form>
            </div>
        </aside>
        <section aria-label="Rezultate">
            <div class="toolbar"><p class="muted" style="margin: 0">{{ $products->total() }} {{ $products->total() === 1 ? 'ramă' : 'rame' }}</p></div>
            @if ($cards->isEmpty())
                <div class="empty">
                    <h2>Nicio ramă nu se potrivește filtrelor alese</h2>
                    <p>Scoateți unul dintre filtre sau căutați după marcă ori cod.</p>
                    <a class="btn" href="{{ $basePath }}">Vezi toate ramele</a>
                </div>
            @else
                <div class="grid">
                    @foreach ($cards as $card)
                        @include('storefront::partials.card', ['card' => $card])
                    @endforeach
                </div>
                {{ $products->onEachSide(1)->links('storefront::partials.pagination') }}
            @endif
        </section>
    </div>
</div>
@endsection
