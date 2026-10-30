@extends('layouts.panou')
@section('title', 'Comenzi')
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@php($O = \App\Modules\Orders\Models\Order::class)
@section('content')
    <x-panou.page-head title="Comenzi" sub="Trei stări separate: comercială, plată, producție.">
        <x-slot:actions>
            @can('orders.export')<a class="btn" href="{{ route('panou.orders.export') }}"><x-panou.icon name="upload" />Export CSV (ultima lună)</a>@endcan
        </x-slot:actions>
    </x-panou.page-head>
    <x-panou.panel :flush="true">
        <form class="filters" method="get" role="search">
            <x-panou.field name="q" type="search" label="Număr, email, telefon, ref. partener" :value="$filters['q'] ?? ''" />
            <x-panou.field name="commercial_status" type="select" label="Comercial" :options="$O::COMMERCIAL" :value="$filters['commercial_status'] ?? ''" placeholder="toate" />
            <x-panou.field name="payment_status" type="select" label="Plată" :options="$O::PAYMENT" :value="$filters['payment_status'] ?? ''" placeholder="toate" />
            <x-panou.field name="payment_method" type="select" label="Metodă" :options="$O::PAYMENT_METHODS" :value="$filters['payment_method'] ?? ''" placeholder="toate" />
            <x-panou.field name="channel" type="select" label="Canal" :options="['b2c' => 'B2C', 'b2b' => 'B2B']" :value="$filters['channel'] ?? ''" placeholder="toate" />
            <button class="btn" type="submit">Filtrează</button>
            <a class="btn btn--ghost" href="?de_actionat=1">De acționat</a>
        </form>
        @if ($orders->isEmpty())
            <x-panou.empty title="Nicio comandă pentru filtrele alese" />
        @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Comanda</th><th>Data</th><th>Client</th><th>Comercial</th><th>Plată</th><th>Producție</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($orders as $o)
                <tr>
                    <td><a class="row-link" href="{{ route('panou.orders.show', $o->uuid) }}">{{ $o->number }}</a>@if($o->channel === 'b2b') <x-panou.badge tone="info">B2B</x-panou.badge>@endif @if($o->is_archived_import) <x-panou.badge>arhivă</x-panou.badge>@endif</td>
                    <td>{{ $o->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $o->organization?->name ?? $o->email }}</td>
                    <td><x-panou.badge :tone="['noua' => 'warn', 'confirmata' => 'info', 'finalizata' => 'ok', 'anulata' => 'danger'][$o->commercial_status] ?? null">{{ $o->commercialLabel() }}</x-panou.badge></td>
                    <td><x-panou.badge :tone="['platita' => 'ok', 'esuata' => 'danger', 'in_asteptare' => 'warn'][$o->payment_status] ?? null">{{ $o->paymentLabel() }}</x-panou.badge><br><span class="small muted">{{ $o->paymentMethodLabel() }}</span></td>
                    <td>{{ $o->production_status === 'nu_se_aplica' ? '—' : $o->production_status }}</td>
                    <td class="num">{{ $fmt($o->total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
        <x-slot:footer>{{ $orders->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
