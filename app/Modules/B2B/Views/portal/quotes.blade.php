@extends('b2b::portal.shell')
@section('title', 'Oferte')
@section('heading', 'Oferte')
@section('body')
<div class="layout-2">
    <div class="box">
        @if ($quotes->isEmpty())<p class="muted">Nu aveți oferte.</p>@else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Oferta</th><th>Data</th><th>Stare</th><th>Valabilă până la</th><th class="num">Total</th></tr></thead>
            <tbody>@foreach ($quotes as $q)<tr><td><a href="/portal/oferte/{{ $q->id }}/">{{ $q->number }}</a></td><td>{{ $q->created_at->format('d.m.Y') }}</td><td>{{ $q->statusLabel() }}</td><td>{{ $q->currentVersion?->valid_until?->format('d.m.Y') ?? '—' }}</td><td class="num">{{ $q->currentVersion && $q->status !== 'ceruta' ? $fmt($q->currentVersion->total) : '—' }}</td></tr>@endforeach</tbody>
        </table></div>
        {{ $quotes->links('storefront::partials.pagination') }}
        @endif
    </div>
    @if ($partner->can('quote'))
    <form method="post" action="/portal/oferte/" class="box form">
        @csrf
        <h2>Cere o ofertă</h2>
        <div class="field"><label for="cerere">Ce doriți să vă ofertăm?</label><textarea id="cerere" name="cerere" rows="6" required minlength="10" placeholder="Ex.: 40 de perechi de lentile monofocale 1,60 cu antireflex pe lună; condiții pentru volume.">{{ old('cerere') }}</textarea><span class="hint">Nu includeți date ale pacienților.</span></div>
        <button class="btn btn--primary" type="submit">Trimite cererea</button>
    </form>
    @endif
</div>
@endsection
