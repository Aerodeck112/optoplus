{{-- Pagina comenzii: starea reală (comercială, plată, producție). Revenirea de la procesator nu marchează plata. --}}
@extends('layouts.site')
@section('title', 'Comanda '.$order->number)
@section('robots', 'noindex, nofollow')
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
<div class="wrap page-pad" @if($analytics) data-module="analytics" data-purchase='@json($analytics)' @endif>
    <h1>Comanda {{ $order->number }}</h1>
    @if ($errors->any())<div class="notice notice--error" role="alert"><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if ($order->commercial_status === 'anulata')
        <div class="notice notice--error"><x-site.icon name="warn" /><p>Comanda a fost anulată.@if($order->cancel_reason) {{ $order->cancel_reason }}@endif</p></div>
    @elseif ($pendingCard && $order->payment_status === 'esuata')
        <div class="notice notice--error" role="alert"><x-site.icon name="warn" /><div><p><strong>Plata nu a reușit.</strong> Comanda este salvată; puteți încerca din nou.</p>
            <form method="post" action="/comanda/{{ $order->uuid }}/plateste/">@csrf<button class="btn btn--primary btn--small">Încearcă din nou plata</button></form></div></div>
    @elseif ($pendingCard)
        <div class="notice" role="status" data-module="refresh" data-refresh="15"><x-site.icon name="info" /><div><p><strong>Așteptăm confirmarea plății de la procesator.</strong> De obicei durează câteva secunde; pagina se actualizează. Dacă ați renunțat la plată, o puteți relua.</p>
            <form method="post" action="/comanda/{{ $order->uuid }}/plateste/">@csrf<button class="link-btn">Reia plata cu cardul</button></form></div></div>
    @elseif ($order->isPaid())
        <div class="notice notice--ok" role="status"><x-site.icon name="check" /><p><strong>Mulțumim! Plata a fost confirmată.</strong> Vă trimitem pe email confirmarea și actualizările comenzii.</p></div>
    @else
        <div class="notice notice--ok" role="status"><x-site.icon name="check" /><p><strong>Mulțumim! Am primit comanda.</strong> Vă trimitem pe email confirmarea și actualizările.</p></div>
    @endif

    @if ($bank && ! $order->isPaid() && $order->commercial_status !== 'anulata')
        <section class="box">
            <h2>Date pentru transferul bancar</h2>
            <dl class="facts">
                <dt>Beneficiar</dt><dd>{{ $bank['beneficiary'] }}</dd>
                <dt>IBAN</dt><dd><code>{{ $bank['iban'] }}</code></dd>
                @if ($bank['bank'])<dt>Banca</dt><dd>{{ $bank['bank'] }}</dd>@endif
                <dt>Suma</dt><dd>{{ $fmt($order->balanceDue()) }}</dd>
                <dt>Detalii plată</dt><dd>Comanda {{ $order->number }}</dd>
            </dl>
            <p class="small muted">Comanda intră în producție după ce confirmăm încasarea.</p>
        </section>
    @endif

    <div class="layout-2" style="margin-top: 24px">
        <div>
            @if ($stages->isNotEmpty())
                @php($current = $stages->search(fn ($s) => $s->code === $order->production_status))
                <section class="box">
                    <h2>Etapele comenzii</h2>
                    <ol class="status-list">
                        @foreach ($stages as $i => $s)
                            <li class="{{ $current !== false && $i < $current ? 'is-done' : ($i === $current ? 'is-current' : '') }}"><div><strong>{{ $s->customer_label }}</strong>@if($i === $current && $s->customer_description)<small>{{ $s->customer_description }}</small>@endif</div></li>
                        @endforeach
                    </ol>
                </section>
            @endif
            @foreach ($order->shipments->whereNotNull('awb') as $sh)
                <section class="box"><h2>Livrare</h2><p>AWB {{ $sh->awb }} · {{ \App\Modules\Orders\Models\Shipment::STATUSES[$sh->status] ?? $sh->status }}@if($sh->tracking_url) · <a href="{{ $sh->tracking_url }}" rel="noopener" target="_blank">urmărește coletul</a>@endif</p></section>
            @endforeach
        </div>
        <aside class="summary">
            <h2>Rezumat</h2>
            <ul class="line__parts">
                @foreach ($order->items as $item)<li><span>{{ $item->quantity }} × {{ $item->name }}</span><span>{{ $fmt($item->line_total) }}</span></li>@endforeach
            </ul>
            <dl style="margin-top: 16px">
                @if ($order->discount_total)<dt>Reduceri</dt><dd class="discount">−{{ $fmt($order->discount_total) }}</dd>@endif
                <dt>Livrare</dt><dd>{{ $order->shipping_total ? $fmt($order->shipping_total) : 'gratuit' }}</dd>
                <dt>Plată</dt><dd>{{ $order->paymentMethodLabel() }} · {{ $order->paymentLabel() }}</dd>
            </dl>
            <div class="summary__total"><span>Total</span><span>{{ $fmt($order->total) }}</span></div>
            @if ($order->production_days_min)<p class="summary__note">Timp estimat de producție: {{ $order->production_days_min }}–{{ $order->production_days_max }} zile lucrătoare, plus transportul.</p>@endif
        </aside>
    </div>
</div>
@endsection
