@extends('configurator::partials.shell')
@section('step')
<form method="post" action="/configurator/{{ $cfg->uuid }}/pas/3/" class="form">
    @csrf
    <h1>Alege lentila</h1>
    <p>Îți arătăm variantele care se pot produce pentru rețeta ta, de la cea mai accesibilă. Prețul este pentru lentile fără tratamente; diferența dintre indici e grosimea și greutatea lentilei.</p>
    @php($allGroups = $lensOptions['recommended']->concat($lensOptions['other']))
    @php($selectedGroup = $allGroups->first(fn ($g) => $g['options']->contains(fn ($o) => $o['offering']->id === $cfg->lens_offering_id)))
    @include('configurator::partials.vision-explainer', ['family' => $selectedGroup ? $selectedGroup['family']->code : (in_array($cfg->usage, ['toate', 'nu_stiu'], true) && $allGroups->contains(fn ($g) => $g['family']->code === 'progresiv') ? 'progresiv' : ($allGroups->first()['family']->code ?? 'progresiv')), 'usage' => $cfg->usage])
    @foreach (['recommended' => 'Potrivite pentru utilizarea aleasă', 'other' => 'Alte variante'] as $group => $heading)
        @if ($lensOptions[$group]->isNotEmpty())
            @if ($group === 'other')<details class="faq" @if($cfg->offering && $lensOptions['other']->contains(fn ($g) => $g['options']->contains(fn ($o) => $o['offering']->id === $cfg->lens_offering_id))) open @endif><summary>{{ $heading }} ({{ $lensOptions['other']->count() }})<x-site.icon name="chevron-down" /></summary>@else<h2 style="font-size: 1.3rem; margin-top: 8px">{{ $heading }}</h2>@endif
            @foreach ($lensOptions[$group] as $g)
                <fieldset class="box" style="margin: 12px 0" @if(! $g['available']) aria-describedby="na-{{ $g['product']->id }}" @endif>
                    <legend class="sr-only">{{ $g['product']->name }}</legend>
                    <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: baseline">
                        <h3 style="margin: 0">{{ $g['product']->name }}</h3>
                        @if ($g['product']->made_in_house)<span class="tag tag--lab"><x-site.icon name="lab" :size="16" />Produsă de noi</span>@endif
                    </div>
                    <p class="muted" style="margin: 6px 0 12px">{{ $g['lens']->customer_summary }} <a href="{{ $g['product']->url() }}" target="_blank" rel="noopener">Detalii</a></p>
                    <div class="choice-grid choice-grid--2">
                        @foreach ($g['options'] as $opt)
                            @php($o = $opt['offering'])
                            <label class="choice">
                                <input type="radio" name="oferta" value="{{ $o->id }}" @checked(old('oferta', $cfg->lens_offering_id) == $o->id) @disabled(! $opt['available']) required data-calc data-family="{{ $g['family']->code }}" data-index="{{ $o->material->refractive_index }}">
                                <span class="choice__mark"></span>
                                <span class="choice__body">
                                    <span class="choice__title"><span>Indice {{ $o->material->indexLabel() }}</span><span>{{ \App\Core\Support\Money::format($opt['price']) }}</span></span>
                                    <span class="choice__text">{{ $o->material->customer_description }} · {{ $o->product->sale_unit === 'pair' ? 'preț pe pereche' : 'preț pe bucată' }}</span>
                                    @if (! $opt['available'])<span class="choice__reason" id="na-{{ $g['product']->id }}">{{ $opt['reason'] }}</span>@endif
                                    @foreach ($opt['warnings'] as $w)<span class="choice__text"><x-site.icon name="info" :size="16" /> {{ $w }}</span>@endforeach
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            @if ($group === 'other')</details>@endif
        @endif
    @endforeach
    @include('configurator::partials.thickness-explainer', ['input' => $input, 'indices' => $allGroups->flatMap(fn ($g) => $g['options']->filter(fn ($o) => $o['available'])->map(fn ($o) => $o['offering']->material->refractive_index))->all()])
    <div class="cfg__nav">
        <a class="btn btn--ghost" href="{{ $cfg->stepUrl(2) }}">Înapoi la rețetă</a>
        <button class="btn btn--primary" type="submit">Continuă la tratamente</button>
    </div>
</form>
@endsection
