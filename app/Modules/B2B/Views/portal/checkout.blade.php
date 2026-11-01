@extends('b2b::portal.shell')
@section('title', 'Finalizare comandă')
@section('heading', 'Finalizare comandă')
@section('body')
<form method="post" action="/portal/finalizare/" class="layout-2" data-module="portal">
    @csrf
    <input type="hidden" name="cheie" value="{{ $key }}"><input type="hidden" name="total_afisat" value="{{ $quote->totalGross }}">
    <div>
        <section class="box form">
            <h2>Livrare</h2>
            @forelse ($methods as $m)
                <label class="choice"><input type="radio" name="livrare" value="{{ $m->code }}" @checked($chosen === $m->code) data-reload-param="livrare"><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">{{ $m->name }}</span><span class="choice__text">{{ $m->customer_note }}</span></span></label>
            @empty
                <p>Nicio metodă de livrare activă. Contactați-ne.</p>
            @endforelse
            <noscript><p class="small">După ce schimbați livrarea, <a href="/portal/finalizare/">reîncărcați pagina</a> pentru totalul actualizat.</p></noscript>
            <div class="field"><label for="adresa_livrare">Adresa de livrare</label><textarea id="adresa_livrare" name="adresa_livrare" rows="3" required>{{ old('adresa_livrare', $org->billing_address) }}</textarea></div>
        </section>
        <section class="box form">
            <h2>Plată și referințe</h2>
            @forelse ($paymentMethods as $code => $pm)
                <label class="choice"><input type="radio" name="plata" value="{{ $code }}" @checked($loop->first)><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">{{ $pm['label'] }}</span><span class="choice__text">{{ $pm['note'] }}</span></span></label>
            @empty
                <p>Nicio metodă de plată disponibilă. Contactați-ne pentru activarea plății la termen sau prin transfer.</p>
            @endforelse
            <div class="field"><label for="po">Numărul comenzii dumneavoastră <span class="hint">(opțional)</span></label><input id="po" name="po" maxlength="80" value="{{ old('po') }}"></div>
            <div class="field"><label for="observatii">Observații <span class="hint">(fără date ale pacienților)</span></label><textarea id="observatii" name="observatii" rows="3" maxlength="1000">{{ old('observatii') }}</textarea></div>
        </section>
    </div>
    <aside class="summary">
        <h2>Rezumat</h2>
        <dl>
            <dt>Lucrări</dt><dd>{{ count($quote->lines) }}</dd>
            <dt>Fără TVA</dt><dd>{{ $fmt($quote->totalNet) }}</dd>
            <dt>Livrare</dt><dd>{{ $quote->shipping ? $fmt($quote->shipping['gross']) : '—' }}</dd>
        </dl>
        <div class="summary__total"><span>{{ \App\Core\Support\Tax::total() }}</span><span>{{ $fmt($quote->totalGross) }}</span></div>
        @foreach ($quote->errors as $e)<p class="error-text">{{ $e }}</p>@endforeach
        <button class="btn btn--primary btn--block" type="submit" style="margin-top: 16px" @disabled(! $paymentMethods || ! $quote->isValid() || $itemErrors)>Trimite comanda</button>
        <p class="summary__note">Fiecare rețetă este verificată de un specialist optic înainte de producție.</p>
    </aside>
</form>
@endsection
