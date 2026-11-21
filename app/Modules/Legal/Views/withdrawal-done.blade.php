@extends('layouts.site')
@section('title', 'Declarația de retragere a fost trimisă')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap page-pad legal-flow">
    <h1>Am primit declarația de retragere</h1>
    <div class="notice notice--ok" role="status"><x-site.icon name="check" /><div>
        <p>Declarația pentru comanda <strong>{{ $withdrawal->order_reference }}</strong> a fost transmisă la <strong>{{ $withdrawal->submitted_at->format('d.m.Y H:i') }}</strong>.</p>
        <p>@if ($withdrawal->confirmation_sent_at)V-am trimis confirmarea de primire pe email, la {{ $withdrawal->email }}.@else Confirmarea pe email nu a putut fi trimisă acum; declarația este însă înregistrată, iar echipa vă contactează.@endif</p>
    </div></div>
    <p>Vă contactăm cu pașii următori pentru returnarea produselor. Dacă ați plătit, restituim banii în cel mult 14 zile de la data declarației, cu aceeași metodă de plată; putem amâna restituirea până primim produsele înapoi.</p>
    <p><a class="btn btn--ghost" href="/">Înapoi la magazin</a></p>
</div>
@endsection
