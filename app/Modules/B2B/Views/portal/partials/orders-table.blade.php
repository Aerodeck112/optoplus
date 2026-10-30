@if ($orders->isEmpty())
    <p class="muted">Nu există comenzi.</p>
@else
<div class="table-wrap"><table class="table">
    <thead><tr><th>Comanda</th><th>Data</th><th>Ref. internă</th><th>Lucrări</th><th>Producție</th><th>Plată</th><th class="num">Total</th></tr></thead>
    <tbody>
    @foreach ($orders as $o)
        <tr>
            <td><a href="/portal/comenzi/{{ $o->uuid }}/">{{ $o->number }}</a></td>
            <td>{{ $o->created_at->format('d.m.Y') }}</td>
            <td>{{ $o->purchase_order_ref ?? '—' }}</td>
            <td>{{ $o->items_count ?? $o->items()->count() }}</td>
            <td>{{ $o->commercial_status === 'anulata' ? 'Anulată' : (\App\Modules\Production\Models\ProductionStage::where('code', $o->production_status)->value('customer_label') ?? '—') }}</td>
            <td>{{ $o->paymentLabel() }}</td>
            <td class="num">{{ \App\Core\Support\Money::format($o->total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endif
