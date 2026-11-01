@extends('layouts.panou')
@section('title', 'Oferte B2B')
@section('content')
    <x-panou.page-head title="Oferte B2B" />
    <nav class="tabs">@foreach (['' => 'Toate', 'ceruta' => 'Cerute', 'ciorna' => 'În pregătire', 'trimisa' => 'Trimise', 'acceptata' => 'Acceptate'] as $k => $l)<a href="?stare={{ $k }}" @if((string) $status === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
    <x-panou.panel :flush="true">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Oferta</th><th>Partener</th><th>Stare</th><th>Valabilă</th><th class="num">Total</th></tr></thead>
            <tbody>@forelse ($quotes as $q)<tr><td><a class="row-link" href="{{ route('panou.b2b.quote', $q->id) }}">{{ $q->number }}</a><br><span class="small muted">{{ $q->created_at->format('d.m.Y') }}</span></td><td>{{ $q->organization?->name }}</td><td>{{ $q->statusLabel() }}</td><td>{{ $q->currentVersion?->valid_until?->format('d.m.Y') ?? '—' }}</td><td class="num">{{ $q->currentVersion ? \App\Core\Support\Money::format($q->currentVersion->total) : '—' }}</td></tr>@empty<tr><td colspan="5" class="muted">Nicio ofertă.</td></tr>@endforelse</tbody>
        </table></div>
        <x-slot:footer>{{ $quotes->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
