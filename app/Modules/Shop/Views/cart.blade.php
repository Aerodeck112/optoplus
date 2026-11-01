{{-- Coșul: liniile și sumele vin din motorul de preț (recalculate la fiecare afișare). --}}
@extends('layouts.site')
@section('title', 'Coșul meu')
@section('robots', 'noindex, nofollow')
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
<div class="wrap page-pad">
    <h1>Coșul meu</h1>
    @if ($errors->any())<div class="notice notice--error" role="alert"><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if ($items->isEmpty())
        <div class="empty">
            <p>Coșul este gol.</p>
            <p><a class="btn btn--primary" href="/configurator/">Configurează ochelarii</a> <a class="btn" href="/rame/">Vezi ramele</a></p>
        </div>
    @else
    <form method="post" action="/cos/actualizeaza/" class="layout-2">
        @csrf
        <div class="box">
            @foreach ($items as $item)
                @php($line = $quote?->lines['i'.$item->id] ?? null)
                @php($cfg = $item->configuration)
                @php($product = $item->kind === 'product' ? $item->variant?->product : $cfg?->frameVariant?->product)
                <article class="line">
                    <div class="line__img"><x-site.picture :media="$product?->images->first()?->media" :alt="$product?->name ?? 'Lentile'" sizes="120px" /></div>
                    <div>
                        <h2 class="line__title h-reset">
                            @if ($item->kind === 'configuration')Ochelari configurați @if($cfg?->entry === 'fara_rama')(doar lentile)@endif @else{{ $line['label'] ?? $item->variant?->displayName() }}@endif
                        </h2>
                        @if ($line)
                            <ul class="line__parts">
                                @foreach ($line['components'] as $c)
                                    <li><span>{{ $c->name }}@if($c->type === 'lens')<span class="tag tag--lab">produse de noi</span>@endif</span>
                                        <span>@if($c->discountTotal())<del>{{ $fmt($c->amount()) }}</del> @endif{{ $c->included ? 'inclus' : $fmt($c->gross) }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                        @foreach (($itemErrors['i'.$item->id] ?? []) as $e)<p class="error-text">{{ $e }}</p>@endforeach
                        @foreach (($line['errors'] ?? []) as $e)<p class="error-text">{{ $e }}</p>@endforeach
                        @if ($item->kind === 'configuration' && $cfg?->status === 'necesita_verificare')
                            <p class="small muted"><x-site.icon name="shield" :size="16" /> Un specialist verifică rețeta înainte de producție.</p>
                        @endif
                        <div class="line__actions">
                            <label class="small">Cantitate <input class="qty-input" type="number" name="cantitate[{{ $item->id }}]" value="{{ $item->quantity }}" min="0" max="20" inputmode="numeric"></label>
                            @if ($item->kind === 'configuration' && $cfg)<a class="small" href="{{ $cfg->stepUrl(5) }}">Modifică configurația</a>@endif
                            <button class="link-btn" type="submit" name="sterge" value="{{ $item->id }}">Elimină</button>
                        </div>
                    </div>
                    <div class="line__total">@if($line){{ $fmt($quote->lineTotal('i'.$item->id)) }}@endif</div>
                </article>
            @endforeach
            <button class="btn btn--ghost btn--small" type="submit">Actualizează coșul</button>
        </div>

        <aside class="summary" aria-labelledby="total-cos">
            <h2 id="total-cos">Total</h2>
            @if ($quote)
                <dl>
                    <dt>Produse</dt><dd>{{ $fmt($quote->subtotal) }}</dd>
                    @if ($quote->discountTotal)<dt>Reduceri</dt><dd>−{{ $fmt($quote->discountTotal) }}</dd>@endif
                    @foreach ($quote->orderDiscounts as $d)<dt class="small">{{ $d['label'] }}</dt><dd class="small">−{{ $fmt($d['amount']) }}</dd>@endforeach
                    <dt>Livrare</dt><dd class="small">la pasul următor</dd>
                </dl>
                <div class="summary__total"><span>Total produse</span><span>{{ $fmt($quote->totalGross) }}</span></div>
                @foreach ($quote->customerMessages() as $m)<p class="small muted">{{ $m }}</p>@endforeach
            @endif
            <div class="coupon">
                <label for="cupon">Cod de reducere</label>
                <div class="inline-field"><input id="cupon" name="cupon" value="{{ $cart->coupon_code }}" maxlength="60" autocomplete="off"><button class="btn btn--small" type="submit">Aplică</button></div>
                @if ($quote?->couponStatus === 'invalid')<p class="error-text">Codul nu este valabil pentru acest coș.</p>@elseif($quote?->couponStatus === 'valid')<p class="small ok">Codul a fost aplicat.</p>@endif
            </div>
            @if ($quote && $quote->isValid() && ! $itemErrors)
                <a class="btn btn--primary btn--block" href="/comanda/">Continuă spre finalizare</a>
            @else
                <p class="error-text">Corectați produsele marcate înainte de a continua.</p>
            @endif
            <p class="small muted">{{ \App\Core\Support\Tax::note() }} Lentilele sunt produse în laboratorul Optoplus după comandă; timpul de producție se adaugă celui de transport.</p>
        </aside>
    </form>
    @endif
</div>
@endsection
