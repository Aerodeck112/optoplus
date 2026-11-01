@extends('layouts.panou')
@section('title', 'Oferta '.$quote->number)
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
    <x-panou.page-head :title="'Oferta '.$quote->number" :crumbs="['Oferte' => route('panou.b2b.quotes'), $quote->number => null]" :sub="($quote->organization?->name ?? '—').' · '.$quote->statusLabel()">
        <x-slot:actions>
            @can('b2b.quotes')@if ($quote->status === 'ciorna' && $quote->current_version_id)<form method="post" action="{{ route('panou.b2b.quote.send', $quote->id) }}">@csrf<button class="btn btn--primary" type="submit"><x-panou.icon name="send" />Trimite partenerului</button></form>@endif @endcan
        </x-slot:actions>
    </x-panou.page-head>
    @if ($quote->order)<div class="alert alert--ok">Acceptată: comanda <a href="{{ route('panou.orders.show', $quote->order->uuid) }}">{{ $quote->order->number }}</a>.</div>@endif
    <div class="grid grid--aside">
        <div class="stack">
            @if ($quote->request_text)<x-panou.panel title="Cererea partenerului"><p>{!! nl2br(e($quote->request_text)) !!}</p></x-panou.panel>@endif
            @foreach ($quote->versions->sortByDesc('number') as $v)
                <x-panou.panel :title="'Versiunea '.$v->number.($v->id === $quote->current_version_id ? ' (curentă)' : '')" :flush="true">
                    <table class="table"><thead><tr><th>Descriere</th><th>Cant.</th><th class="num">Preț</th><th>TVA</th><th class="num">Valoare</th></tr></thead>
                        <tbody>@foreach ($v->items as $i)<tr><td>{{ $i['description'] }}</td><td>{{ $i['quantity'] }}</td><td class="num">{{ $fmt($i['unit_price']) }}</td><td>{{ $i['vat_bp'] / 100 }}%</td><td class="num">{{ $fmt($i['line_total']) }}</td></tr>@endforeach</tbody>
                        <tfoot><tr><td colspan="4" class="text-right">Total {{ $v->prices_include_vat ? '(cu TVA inclus '.$fmt($v->vat_total).')' : '(TVA '.$fmt($v->vat_total).')' }}</td><td class="num"><strong>{{ $fmt($v->total) }}</strong></td></tr></tfoot></table>
                    <x-slot:footer><span class="small muted">Valabilă până la {{ $v->valid_until?->format('d.m.Y') }} · {{ $v->sent_at ? 'trimisă '.$v->sent_at->format('d.m.Y H:i') : 'netrimisă' }}</span></x-slot:footer>
                </x-panou.panel>
            @endforeach
        </div>
        @can('b2b.quotes')
        @if (in_array($quote->status, ['ceruta', 'ciorna', 'trimisa'], true))
        <x-panou.panel title="Versiune nouă">
            <form method="post" action="{{ route('panou.b2b.quote.version', $quote->id) }}" class="stack">@csrf
                @for ($i = 0; $i < 5; $i++)
                    <fieldset class="stack" style="border:1px solid var(--line);border-radius:8px;padding:.6rem">
                        <legend class="small">Linia {{ $i + 1 }}</legend>
                        <x-panou.field :name="'linii['.$i.'][descriere]'" label="Descriere" />
                        <div class="cluster">
                            <x-panou.field :name="'linii['.$i.'][cantitate]'" type="number" label="Cant." />
                            <x-panou.field :name="'linii['.$i.'][pret]'" label="Preț unitar (lei)" />
                            <x-panou.field :name="'linii['.$i.'][tva]'" type="number" label="TVA (bp)" value="2100" hint="2100 = 21%" />
                        </div>
                    </fieldset>
                @endfor
                <x-panou.field name="cu_tva" type="checkbox" label="Prețurile introduse includ TVA" />
                <x-panou.field name="valabila" type="date" label="Valabilă până la" :value="now()->addDays(14)->format('Y-m-d')" required />
                <x-panou.field name="conditii" type="textarea" label="Condiții (termen de livrare, plată)" hint="Nu promiteți termene de producție neconfirmate." />
                <x-panou.field name="mesaj" type="textarea" label="Mesaj pentru partener" />
                <button class="btn btn--primary" type="submit">Salvează versiunea</button>
            </form>
        </x-panou.panel>
        @endif
        @endcan
    </div>
@endsection
