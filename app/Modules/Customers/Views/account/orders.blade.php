@extends('customers::account.shell')
@section('title', 'Comenzile mele')
@section('heading', 'Bună, '.($customer->first_name ?: 'bine ați revenit'))
@section('body')
@if ($orders->isEmpty())
    <div class="empty"><p>Nu aveți încă nicio comandă în acest cont.</p><p><a class="btn btn--primary" href="/configurator/">Configurează ochelarii</a></p></div>
@else
    <div class="box table-wrap">
        <table class="table">
            <thead><tr><th>Comanda</th><th>Data</th><th>Produse</th><th>Stare</th><th class="num">Total</th></tr></thead>
            <tbody>
            @foreach ($orders as $o)
                <tr>
                    <td><a href="/comanda/{{ $o->uuid }}/">{{ $o->number }}</a></td>
                    <td>{{ $o->created_at->format('d.m.Y') }}</td>
                    <td>{{ $o->items->sum('quantity') }}</td>
                    <td>{{ $o->commercial_status === 'anulata' ? 'Anulată' : ($o->requiresProduction() ? (\App\Modules\Production\Models\ProductionStage::where('code', $o->production_status)->value('customer_label') ?? $o->commercialLabel()) : $o->commercialLabel()) }}<br><small class="muted">{{ $o->paymentLabel() }}</small></td>
                    <td class="num">{{ \App\Core\Support\Money::format($o->total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $orders->links('storefront::partials.pagination') }}
@endif
@endsection
