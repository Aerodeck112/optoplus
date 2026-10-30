@extends('b2b::portal.shell')
@section('title', 'Oferta '.$quote->number)
@section('heading', 'Oferta '.$quote->number)
@section('body')
<p><span class="tag">{{ $quote->statusLabel() }}</span>@if($quote->order) · comanda <a href="/portal/comenzi/{{ $quote->order->uuid }}/">{{ $quote->order->number }}</a>@endif</p>
@if ($quote->request_text)<section class="box"><h2>Cererea dumneavoastră</h2><p>{!! nl2br(e($quote->request_text)) !!}</p></section>@endif
@if ($version)
<section class="box">
    <h2>Versiunea {{ $version->number }}@if($version->valid_until) · valabilă până la {{ $version->valid_until->format('d.m.Y') }}@endif</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Descriere</th><th>Cant.</th><th class="num">Preț unitar</th><th class="num">Valoare</th></tr></thead>
        <tbody>@foreach ($version->items as $i)<tr><td>{{ $i['description'] }}</td><td>{{ $i['quantity'] }}</td><td class="num">{{ $fmt($i['unit_price']) }}</td><td class="num">{{ $fmt($i['line_total']) }}</td></tr>@endforeach</tbody>
    </table></div>
    <dl class="facts" style="margin-top: 16px">
        <dt>Subtotal</dt><dd>{{ $fmt($version->subtotal) }} {{ $version->prices_include_vat ? 'cu TVA' : 'fără TVA' }}</dd>
        @if ($version->discount_total)<dt>Reducere</dt><dd>−{{ $fmt($version->discount_total) }}</dd>@endif
        <dt>TVA</dt><dd>{{ $fmt($version->vat_total) }}</dd>
        <dt>Total</dt><dd><strong>{{ $fmt($version->total) }}</strong></dd>
    </dl>
    @if ($version->terms)<p class="small">{!! nl2br(e($version->terms)) !!}</p>@endif
    @if ($version->note_to_customer)<p>{!! nl2br(e($version->note_to_customer)) !!}</p>@endif
    @if ($quote->status === 'trimisa' && ! $version->isExpired() && $partner->can('quote'))
        <form method="post" action="/portal/oferte/{{ $quote->id }}/accepta/" class="form" style="margin-top: 16px">
            @csrf
            <input type="hidden" name="cheie" value="{{ $key }}">
            <div class="grid-2">
                <div class="field"><label for="plata">Plata</label><select id="plata" name="plata">@if($bank)<option value="transfer_bancar">Transfer bancar</option>@endif @if($canCredit)<option value="termen">La termen, pe factură</option>@endif</select></div>
                <div class="field"><label for="po">Numărul comenzii dvs. <span class="hint">(opțional)</span></label><input id="po" name="po" maxlength="80"></div>
            </div>
            <label class="check"><input type="checkbox" name="accept" value="1" required><span>Accept oferta în condițiile de mai sus și plasez comanda.</span></label>
            <button class="btn btn--primary" type="submit" @disabled(! $bank && ! $canCredit)>Acceptă oferta</button>
        </form>
    @endif
</section>
@elseif ($quote->status === 'ceruta')
    <p class="muted">Pregătim oferta. Vă anunțăm pe email când este gata.</p>
@endif
@endsection
