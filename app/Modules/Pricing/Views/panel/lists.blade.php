@extends('layouts.panou')
@section('title', 'Liste de preț B2B')
@section('content')
    <x-panou.page-head title="Liste de preț B2B" sub="Fiecare partener aprobat poate avea o listă. Prețurile din listă se văd doar în portalul partenerului." />
    <x-panou.panel :flush="true">
        <table class="table"><thead><tr><th>Lista</th><th>Cod</th><th>Prețuri</th><th>Parteneri</th><th>Rezervă</th><th>Activă</th></tr></thead>
            <tbody>@forelse ($lists as $l)<tr><td><a class="row-link" href="{{ route('panou.pricing.lists.show', $l->id) }}">{{ $l->name }}</a></td><td><code>{{ $l->code }}</code></td><td>{{ $l->items_count }} ({{ $l->prices_include_vat ? 'cu TVA' : 'fără TVA' }})</td><td>{{ $usage[$l->id] ?? 0 }}</td>
                <td class="small">{{ ['none' => 'fără preț → ofertă', 'b2c' => 'preț B2C', 'b2c_discount' => 'B2C −'.($l->fallback_discount_bp / 100).'%'][$l->fallback] ?? $l->fallback }}</td><td>{{ $l->is_active ? 'da' : 'nu' }}</td></tr>
            @empty<tr><td colspan="6" class="muted">Nicio listă.</td></tr>@endforelse</tbody></table>
    </x-panou.panel>
    @can('pricing.manage')
    <x-panou.panel title="Listă nouă">
        @include('pricing::panel.list-form', ['list' => null])
    </x-panou.panel>
    @endcan
@endsection
