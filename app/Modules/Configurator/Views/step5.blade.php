@extends('configurator::partials.shell')
@php($rx = fn (?\App\Modules\Configurator\Support\EyeRx $e) => $e ? implode(' · ', array_filter(['SPH '.\App\Modules\Configurator\Support\EyeRx::format($e->sph), $e->cyl !== null && $e->cyl !== 0 ? 'CYL '.\App\Modules\Configurator\Support\EyeRx::format($e->cyl).' × '.$e->axis.'°' : null, $e->add ? 'ADD '.\App\Modules\Configurator\Support\EyeRx::format($e->add, false) : null])) : '—')
@section('step')
<form method="post" action="/configurator/{{ $cfg->uuid }}/in-cos/" class="form">
    @csrf
    <h1>Rezumat</h1>
    <dl class="facts">
        @if ($frame)<dt>Rama</dt><dd>{{ $frame->displayName() }} <a class="small" href="{{ $frame->product->url() }}">schimbă</a></dd>@endif
        <dt>Utilizare</dt><dd>{{ \App\Modules\Configurator\Support\ConfigurationInput::USAGES[$cfg->usage] ?? '—' }} <a class="small" href="{{ $cfg->stepUrl(1) }}">schimbă</a></dd>
        <dt>Lentile</dt><dd>{{ $offering ? $offering->product->name.', indice '.$offering->material->indexLabel().' ('.($offering->product->sale_unit === 'pair' ? 'pereche' : 'bucată').')' : '—' }} <a class="small" href="{{ $cfg->stepUrl(3) }}">schimbă</a></dd>
        <dt>Tratamente</dt><dd>{{ collect($quote->components())->where('type', 'treatment')->pluck('name')->implode(', ') ?: 'fără' }} <a class="small" href="{{ $cfg->stepUrl(4) }}">schimbă</a></dd>
        @if ($input->eyes !== 'os')<dt>Ochiul drept</dt><dd>{{ $rx($input->od) }}</dd>@endif
        @if ($input->eyes !== 'od')<dt>Ochiul stâng</dt><dd>{{ $rx($input->os) }}</dd>@endif
        <dt>DP</dt><dd>@if($input->pdMode === 'binocular'){{ ($input->measurements['pd'] ?? 0) / 10 }} mm @elseif($input->pdMode === 'monocular'){{ ($input->measurements['pd_od'] ?? 0) / 10 }} / {{ ($input->measurements['pd_os'] ?? 0) / 10 }} mm @else ne contactați @endif <a class="small" href="{{ $cfg->stepUrl(2) }}">schimbă</a></dd>
    </dl>
    @if (! $validation->isValid())
        <div class="notice notice--error" role="alert"><x-site.icon name="warn" /><div><p><strong>Configurația nu poate fi comandată încă:</strong></p><ul>@foreach ($validation->errors as $e)<li>{{ $e['message'] }}</li>@endforeach</ul></div></div>
    @endif
    @foreach ($validation->warnings as $w)
        <div class="notice notice--warn"><x-site.icon name="info" /><p>{{ $w['message'] }}</p></div>
    @endforeach
    @if ($validation->requiresReview())
        <div class="notice"><x-site.icon name="shield" /><div><p><strong>Ce urmează după comandă:</strong> un specialist optic verifică rețeta și lentila aleasă înainte de producție.</p>
            @if (count($validation->reviewReasons) > 1)<ul>@foreach (array_slice($validation->reviewReasons, 1) as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
            <p class="small">Dacă trebuie schimbat ceva, vă contactăm înainte de producție.</p></div></div>
    @endif
    <label class="check"><input type="checkbox" name="acord_reteta" value="1" required @checked(old('acord_reteta'))><span>Sunt de acord cu prelucrarea rețetei mele (date privind sănătatea) de către Optoplus, pentru realizarea și verificarea lentilelor. <a href="/politica-de-confidentialitate/" target="_blank" rel="noopener">Detalii</a></span></label>
    <div class="cfg__nav">
        <a class="btn btn--ghost" href="{{ $cfg->stepUrl(4) }}">Înapoi</a>
        <button class="btn btn--primary" type="submit" @disabled(! $validation->isValid())>Adaugă în coș</button>
    </div>
</form>
<form method="post" action="/configurator/{{ $cfg->uuid }}/link/" style="margin-top: 16px">
    @csrf
    <button class="link-btn" type="submit">Salvează și continuă mai târziu (primești un link securizat)</button>
</form>
@endsection
