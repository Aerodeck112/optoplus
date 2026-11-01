@extends('b2b::portal.shell')
@section('title', 'Coș')
@section('heading', 'Coșul organizației')
@section('body')
@if ($items->isEmpty())
    <div class="empty"><p>Coșul este gol.</p><p><a class="btn btn--primary" href="/portal/comanda-rapida/">Comandă rapidă</a></p></div>
@else
<div class="layout-2">
    <div class="box table-wrap">
        <table class="table">
            <thead><tr><th>Cod pacient</th><th>Lucrare</th><th>Buc.</th><th class="num">Valoare</th><th></th></tr></thead>
            <tbody>
            @foreach ($items as $item)
                @php($line = $quote?->lines['i'.$item->id] ?? null)
                <tr>
                    <td>{{ $item->patient_reference ?? '—' }}</td>
                    <td>{{ $line['label'] ?? '—' }}
                        @foreach (($itemErrors['i'.$item->id] ?? []) as $e)<br><span class="error-text">{{ $e }}</span>@endforeach
                        @foreach (($line['errors'] ?? []) as $e)<br><span class="error-text">{{ $e }}</span>@endforeach</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="num">@if($line){{ $fmt($partner->showVat() ? $quote->lineTotal('i'.$item->id) : collect($line['components'])->sum('net')) }}@endif</td>
                    <td><form method="post" action="/portal/cos/elimina/">@csrf<button class="link-btn" name="sterge" value="{{ $item->id }}">Elimină</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <aside class="summary">
        <h2>Total</h2>
        <dl>
            <dt>Fără TVA</dt><dd>{{ $fmt($quote->totalNet) }}</dd>
            @if (\App\Core\Support\Tax::payer())<dt>TVA</dt><dd>{{ $quote->vatConfirmed() ? $fmt($quote->vatTotal) : 'se confirmă pe factură' }}</dd>@endif
        </dl>
        <div class="summary__total"><span>{{ \App\Core\Support\Tax::total() }}</span><span>{{ $fmt($quote->totalGross) }}</span></div>
        @if ($quote->isValid() && ! $itemErrors)
            <a class="btn btn--primary btn--block" href="/portal/finalizare/" style="margin-top: 16px">Continuă</a>
        @else
            <p class="error-text">Corectați sau eliminați lucrările marcate.</p>
        @endif
    </aside>
</div>
@endif
@endsection
