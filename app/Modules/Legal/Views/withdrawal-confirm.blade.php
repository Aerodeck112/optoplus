@extends('layouts.site')
@section('title', 'Confirmați retragerea')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap page-pad legal-flow">
    <h1>Confirmați retragerea</h1>
    <p class="lead">Verificați datele. După ce apăsați „Confirmați retragerea”, declarația se transmite și primiți confirmarea pe email.</p>
    <div class="box">
        <dl class="kv">
            <dt>Nume</dt><dd>{{ $draft['nume'] }}</dd>
            <dt>Comanda</dt><dd>{{ $draft['comanda'] }}</dd>
            <dt>Email pentru confirmare</dt><dd>{{ $draft['email'] }}</dd>
            @if (! empty($draft['detalii']))<dt>Produse / mesaj</dt><dd>{!! nl2br(e($draft['detalii'])) !!}</dd>@endif
        </dl>
        <form method="post" action="/retragere/confirmare/" class="cluster" style="margin-top:16px">@csrf
            <button class="btn btn--primary" type="submit">Confirmați retragerea</button>
            <a class="btn btn--ghost" href="/retragere/">Modifică datele</a>
        </form>
    </div>
</div>
@endsection
