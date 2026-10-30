{{-- Finalizarea comenzii: o pagină, funcțională fără JavaScript. Totalul vine din motorul de preț. --}}
@extends('layouts.site')
@section('title', 'Finalizează comanda')
@section('robots', 'noindex, nofollow')
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@php($v = fn ($k, $d = null) => old($k, $d))
@section('content')
<div class="wrap page-pad">
    <h1>Finalizează comanda</h1>
    @if ($errors->any())
        <div class="notice notice--error" role="alert" tabindex="-1" data-focus-on-load><x-site.icon name="warn" /><div><p><strong>Ce trebuie corectat:</strong></p><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif

    @if ($shippingMethods->isEmpty() || ! $paymentMethods)
        <div class="notice notice--warn"><x-site.icon name="info" /><div>
            <p><strong>Comenzile online nu sunt încă deschise pentru această combinație de livrare și plată.</strong></p>
            <p>Configurația rămâne salvată în coș. Sunați-ne la <a href="tel:{{ preg_replace('/\s+/', '', (string) app(\App\Core\Settings\Settings::class)->get('contact.phone_b2c')) }}">{{ app(\App\Core\Settings\Settings::class)->get('contact.phone_b2c') }}</a> și finalizăm comanda împreună.</p>
        </div></div>
    @endif

    <form method="post" action="/comanda/" class="layout-2" data-module="checkout" novalidate>
        @csrf
        <input type="hidden" name="cheie" value="{{ $key }}">
        <input type="hidden" name="total_afisat" value="{{ $quote->totalGross }}">
        <div>
            <section class="box form" aria-labelledby="date-contact">
                <h2 id="date-contact">Date de contact</h2>
                <div class="grid-2">
                    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required value="{{ $v('email', $customer?->email) }}"></div>
                    <div class="field"><label for="telefon">Telefon</label><input id="telefon" name="telefon" type="tel" autocomplete="tel" required value="{{ $v('telefon', $customer?->phone) }}"><span class="hint">Vă sunăm dacă specialistul are o întrebare despre rețetă.</span></div>
                </div>
                @unless ($customer)<p class="small muted">Aveți cont? <a href="/cont/autentificare/?inapoi=/comanda/">Autentificați-vă</a> ca să vedeți comanda și etapele producției în cont. Puteți comanda și fără cont.</p>@endunless
            </section>

            <section class="box form" aria-labelledby="facturare">
                <h2 id="facturare">Date de facturare</h2>
                <fieldset class="choices choices--inline">
                    <legend class="sr-only">Tip client</legend>
                    <label class="check"><input type="radio" name="tip" value="pf" @checked($v('tip', 'pf') === 'pf')><span>Persoană fizică</span></label>
                    <label class="check"><input type="radio" name="tip" value="pj" @checked($v('tip') === 'pj')><span>Firmă</span></label>
                </fieldset>
                <div class="field"><label for="nume">Nume și prenume</label><input id="nume" name="nume" autocomplete="name" required value="{{ $v('nume', $address?->name ?? trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? ''))) }}"></div>
                <div class="grid-2" data-show-if="tip=pj">
                    <div class="field"><label for="firma">Denumirea firmei</label><input id="firma" name="firma" autocomplete="organization" value="{{ $v('firma', $address?->company) }}"></div>
                    <div class="field"><label for="cui">CUI</label><input id="cui" name="cui" value="{{ $v('cui', $address?->vat_code) }}"></div>
                    <div class="field"><label for="reg_com">Nr. Registrul Comerțului <span class="hint">(opțional)</span></label><input id="reg_com" name="reg_com" value="{{ $v('reg_com') }}"></div>
                </div>
                <div class="field"><label for="adresa">Adresa (stradă, număr, bloc, apartament)</label><input id="adresa" name="adresa" autocomplete="street-address" required value="{{ $v('adresa', $address?->line1) }}"></div>
                <div class="grid-2">
                    <div class="field"><label for="localitate">Localitatea</label><input id="localitate" name="localitate" autocomplete="address-level2" required value="{{ $v('localitate', $address?->city) }}"></div>
                    <div class="field"><label for="judet">Județul</label><input id="judet" name="judet" autocomplete="address-level1" required value="{{ $v('judet', $address?->county) }}"></div>
                    <div class="field"><label for="cod_postal">Cod poștal <span class="hint">(opțional)</span></label><input id="cod_postal" name="cod_postal" autocomplete="postal-code" inputmode="numeric" value="{{ $v('cod_postal', $address?->postal_code) }}"></div>
                </div>
                <label class="check"><input type="checkbox" name="alta_livrare" value="1" @checked($v('alta_livrare'))><span>Livrez la altă adresă</span></label>
                <div class="form" data-show-if="alta_livrare=1">
                    <div class="field"><label for="livrare_nume">Nume destinatar</label><input id="livrare_nume" name="livrare_nume" value="{{ $v('livrare_nume') }}"></div>
                    <div class="field"><label for="livrare_adresa">Adresa de livrare</label><input id="livrare_adresa" name="livrare_adresa" value="{{ $v('livrare_adresa') }}"></div>
                    <div class="grid-2">
                        <div class="field"><label for="livrare_localitate">Localitatea</label><input id="livrare_localitate" name="livrare_localitate" value="{{ $v('livrare_localitate') }}"></div>
                        <div class="field"><label for="livrare_judet">Județul</label><input id="livrare_judet" name="livrare_judet" value="{{ $v('livrare_judet') }}"></div>
                        <div class="field"><label for="livrare_cod_postal">Cod poștal</label><input id="livrare_cod_postal" name="livrare_cod_postal" value="{{ $v('livrare_cod_postal') }}"></div>
                    </div>
                </div>
            </section>

            <section class="box form" aria-labelledby="livrare-titlu">
                <h2 id="livrare-titlu">Livrare</h2>
                @if ($shippingMethods->isEmpty())
                    <p>Nicio metodă de livrare nu este activă momentan.</p>
                @else
                    <fieldset class="choices choice-grid">
                        <legend class="sr-only">Metoda de livrare</legend>
                        @foreach ($shippingMethods as $m)
                            <label class="choice">
                                <input type="radio" name="livrare" value="{{ $m['model']->code }}" @checked($chosen === $m['model']->code) @disabled(! $m['quote']) data-recalc><span class="choice__mark"></span>
                                <span class="choice__body"><span class="choice__title">{{ $m['model']->name }}</span>
                                    <span class="choice__text">@if($m['model']->transit_days_min)Transport {{ $m['model']->transit_days_min }}–{{ $m['model']->transit_days_max }} zile lucrătoare, după ce comanda este gata. @endif{{ $m['model']->customer_note }}</span></span>
                                <span class="choice__aside">@if($m['quote']){{ $m['quote']['price'] ? $fmt($m['quote']['price']) : 'gratuit' }}@else indisponibil @endif</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <button class="btn btn--ghost btn--small js-hide" type="submit" name="actiune" value="recalculeaza" formnovalidate>Recalculează totalul</button>
                @endif
            </section>

            <section class="box form" aria-labelledby="plata-titlu">
                <h2 id="plata-titlu">Plată</h2>
                @forelse ($paymentMethods as $code => $pm)
                    <label class="choice"><input type="radio" name="plata" value="{{ $code }}" @checked($v('plata', array_key_first($paymentMethods)) === $code)><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">{{ $pm['label'] }}</span><span class="choice__text">{{ $pm['note'] }}</span></span></label>
                @empty
                    <p>Nicio metodă de plată nu este disponibilă pentru livrarea aleasă.</p>
                @endforelse
                <div class="field"><label for="observatii">Observații pentru comandă <span class="hint">(opțional; nu scrieți aici valorile rețetei)</span></label><textarea id="observatii" name="observatii" rows="3" maxlength="1000">{{ $v('observatii') }}</textarea></div>
            </section>
        </div>

        <aside class="summary" aria-labelledby="sumar-comanda">
            <h2 id="sumar-comanda">Comanda ta</h2>
            <ul class="line__parts">
                @foreach ($quote->lines as $line)
                    <li><span>{{ $line['quantity'] }} × {{ \Illuminate\Support\Str::limit($line['label'], 60) }}</span><span>{{ $fmt($quote->lineTotal($line['key'])) }}</span></li>
                @endforeach
            </ul>
            <dl style="margin-top: 16px">
                <dt>Produse</dt><dd>{{ $fmt($quote->subtotal) }}</dd>
                @if ($quote->discountTotal)<dt>Reduceri</dt><dd class="discount">−{{ $fmt($quote->discountTotal) }}</dd>@endif
                <dt>Livrare</dt><dd>{{ $quote->shipping ? ($quote->shipping['gross'] ? $fmt($quote->shipping['gross']) : 'gratuit') : '—' }}</dd>
                @if ($quote->vatConfirmed())<dt><small>din care TVA</small></dt><dd class="small">{{ $fmt($quote->vatTotal) }}</dd>@endif
            </dl>
            <div class="summary__total"><span>Total de plată</span><span>{{ $fmt($quote->totalGross) }}</span></div>
            @foreach ($quote->errors as $e)<p class="error-text">{{ $e }}</p>@endforeach
            <label class="check" style="margin-top: 16px"><input type="checkbox" name="acord_termeni" value="1" required @checked($v('acord_termeni'))><span>Am citit și accept <a href="/termeni-si-conditii/" target="_blank" rel="noopener">termenii și condițiile</a> și <a href="/politica-de-confidentialitate/" target="_blank" rel="noopener">politica de confidențialitate</a>.</span></label>
            <button class="btn btn--primary btn--block" type="submit" name="actiune" value="plaseaza" @disabled(! $paymentMethods || ! $quote->isValid() || $itemErrors) style="margin-top: 16px">Plasează comanda</button>
            <p class="summary__note">Lentilele se produc după comandă, în laboratorul nostru. Un specialist optic verifică rețeta înainte de producție și vă contactează dacă e nevoie de o clarificare.</p>
        </aside>
    </form>
</div>
@endsection
