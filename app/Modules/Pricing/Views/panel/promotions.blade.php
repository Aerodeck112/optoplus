@extends('layouts.panou')
@section('title', 'Promoții și cupoane')
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
    <x-panou.page-head title="Promoții și cupoane" sub="Reducerile se calculează numai pe server, de motorul de preț, în coș, configurator și portalul B2B.">
        <x-slot:actions>@can('pricing.manage')<a class="btn btn--primary" href="{{ route('panou.pricing.promotions.create') }}"><x-panou.icon name="plus" />Promoție nouă</a>@endcan</x-slot:actions>
    </x-panou.page-head>
    <nav class="tabs">@foreach (['active' => 'Active acum', 'inactive' => 'Inactive / expirate', 'toate' => 'Toate'] as $k => $l)<a href="?stare={{ $k }}" @if($filter === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
    <x-panou.panel :flush="true">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Promoția</th><th>Se aplică la</th><th>Reducere</th><th>Perioadă</th><th>Cupoane</th><th>Utilizări</th><th>Stare</th></tr></thead>
            <tbody>@forelse ($promotions as $p)<tr>
                <td><a class="row-link" href="{{ route('panou.pricing.promotions.edit', $p->id) }}">{{ $p->name }}</a>@if($p->source && $p->source !== 'manual')<br><span class="small muted">{{ \Illuminate\Support\Str::limit($p->source, 50) }}</span>@endif</td>
                <td>{{ \App\Modules\Pricing\Models\Promotion::SCOPES[$p->scope] ?? $p->scope }}@if($p->scope_ids) ({{ count($p->scope_ids) }})@endif · {{ strtoupper($p->channel) }}</td>
                <td>{{ $p->type === 'percent' ? ($p->value / 100).'%' : ($p->type === 'fixed_price' ? 'preț fix '.$fmt($p->value) : '−'.$fmt($p->value)) }}</td>
                <td class="small">{{ $p->starts_at?->format('d.m.Y') ?? '—' }} → {{ $p->ends_at?->format('d.m.Y') ?? 'fără sfârșit' }}</td>
                <td>{{ $p->coupons_count ?: ($p->requires_coupon ? '0 (!)' : '—') }}</td>
                <td>{{ $p->uses_count }}@if($p->max_uses)/{{ $p->max_uses }}@endif</td>
                <td><x-panou.badge :tone="$p->is_active && (! $p->ends_at || $p->ends_at->isFuture()) ? 'ok' : null">{{ $p->is_active ? ($p->ends_at && $p->ends_at->isPast() ? 'expirată' : 'activă') : 'inactivă' }}</x-panou.badge></td>
            </tr>@empty<tr><td colspan="7" class="muted">Nicio promoție.</td></tr>@endforelse</tbody>
        </table></div>
        <x-slot:footer>{{ $promotions->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
