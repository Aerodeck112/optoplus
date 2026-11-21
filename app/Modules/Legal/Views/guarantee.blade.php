@extends('layouts.site')
@section('title', 'Garanția legală de conformitate')
@section('content')
@php($lang = in_array(app()->getLocale(), ['ro', 'en', 'hu'], true) ? app()->getLocale() : 'ro')
<div class="wrap page-pad legal-flow">
    <h1>Garanția legală de conformitate</h1>
    <p class="lead">Pentru produsele cumpărate de la noi beneficiați de garanția legală de conformitate de cel puțin doi ani (OUG nr. 140/2021). Mai jos este notificarea armonizată a Uniunii Europene.</p>
    <figure class="legal-notice" data-no-localize>
        <img src="/legal/garantie-legala-{{ $lang }}.jpg" width="1100" height="{{ ['ro' => 1298, 'en' => 1554, 'hu' => 1550][$lang] }}" alt="Notificarea armonizată privind garanția legală de conformitate (Regulamentul (UE) 2025/1960)" translate="no">
        <figcaption class="small muted">Notificare armonizată conform Regulamentului de punere în aplicare (UE) 2025/1960, anexa I.</figcaption>
    </figure>
    <p>Pentru o problemă cu un produs: <a href="/contact/">scrieți-ne</a> cu numărul comenzii și, dacă se poate, câteva fotografii. Vezi și <a href="/politica-ramburs-retur/">politica de retur și rambursare</a>.</p>
</div>
@endsection
