{{-- Cadrul comun al pașilor: progres (linia de referință cu puncte), formular, rezumatul cu prețul calculat pe server. --}}
@extends('layouts.site')
@section('title', 'Configurează ochelarii — '.\App\Modules\Configurator\Models\Configuration::STEPS[$step])
@section('robots', 'noindex, nofollow')
@section('body_class', 'cfg-page')
@section('content')
<div class="wrap" data-module="configurator" data-calc-url="/configurator/{{ $cfg->uuid }}/calcul/" data-step="{{ $step }}">
    <ol class="progress" aria-label="Pașii configurării">
        @foreach (\App\Modules\Configurator\Models\Configuration::STEPS as $n => $label)
            @php($state = $n < $step ? 'is-done' : ($n === $step ? 'is-current' : ''))
            <li class="{{ $state }}">
                @if ($n <= $cfg->step && $n !== $step)
                    <a href="{{ $cfg->stepUrl($n) }}"><span class="dot"></span><span class="label">{{ $label }}</span></a>
                @else
                    <span @if($n === $step) aria-current="step" @endif><span class="dot"></span><span class="label">{{ $label }}</span></span>
                @endif
            </li>
        @endforeach
    </ol>
    <div class="cfg">
        <div class="cfg__panel">
            @if ($errors->any())
                <div class="notice notice--error" role="alert" tabindex="-1" data-focus-on-load><x-site.icon name="warn" /><div><p><strong>Ce trebuie corectat:</strong></p><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
            @endif
            @if (session('share_link'))
                <div class="notice notice--ok" role="status"><x-site.icon name="check" /><div><p><strong>Linkul pentru a continua mai târziu</strong> (valabil {{ config('optoplus.configurator.share_link_days') }} de zile; nu îl distribuiți — conține rețeta dumneavoastră):</p><p><input type="text" readonly value="{{ session('share_link') }}" aria-label="Linkul de reluare"></p></div></div>
            @endif
            @yield('step')
        </div>
        <aside class="summary" aria-labelledby="rezumat-titlu" aria-live="polite" data-summary>
            @include('configurator::partials.summary', ['quote' => $quote, 'cfg' => $cfg, 'validation' => $validation])
        </aside>
    </div>
    <div class="total-bar" aria-hidden="true">
        <div><small>Total estimat</small><strong data-total>{{ \App\Core\Support\Money::format($quote->totalGross) }}</strong></div>
        <a class="btn btn--light btn--small" href="#rezumat-titlu">Vezi detaliile</a>
    </div>
</div>
@endsection
