@extends('b2b::portal.shell')
@section('title', 'Comanda '.$order->number)
@section('heading', 'Comanda '.$order->number)
@section('body')
<div class="layout-2">
    <div>
        <section class="box">
            <h2>Lucrări</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Cod pacient</th><th>Lucrare</th><th>Etapa</th><th class="num">Valoare</th></tr></thead>
                <tbody>
                @foreach ($order->items as $item)
                    @php($job = $order->productionJobs->where('order_item_id', $item->id)->sortByDesc('id')->first())
                    <tr><td>{{ $item->patient_reference ?? '—' }}</td><td>{{ $item->quantity }} × {{ $item->name }}</td>
                        <td>{{ $job ? ($stages[$job->stage] ?? $job->stage) : '—' }}@if($job?->remake_of_id) <span class="tag">refacere</span>@endif</td>
                        <td class="num">{{ $fmt($item->line_total) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        </section>
        <section class="box">
            <h2>Istoric</h2>
            <ol class="status-list">
                @foreach ($order->events->where('visible_to_customer', true) as $e)
                    <li class="is-done"><div><strong>{{ $e->message ?? ($e->to_status ? (\App\Modules\Orders\Models\Order::PAYMENT[$e->to_status] ?? $stages[$e->to_status] ?? \App\Modules\Orders\Models\Order::COMMERCIAL[$e->to_status] ?? $e->to_status) : '') }}</strong><small>{{ $e->created_at->format('d.m.Y H:i') }}</small></div></li>
                @endforeach
            </ol>
        </section>
        @if ($partner->can('order'))
        <section class="box form">
            <h2>Sesizare, reclamație sau refacere</h2>
            <form method="post" action="/portal/comenzi/{{ $order->uuid }}/sesizare/" class="form">
                @csrf
                <div class="grid-2">
                    <div class="field"><label for="tip">Tip</label><select id="tip" name="tip"><option value="reclamatie">Reclamație</option><option value="refacere">Cerere de refacere</option><option value="retur">Retur</option></select></div>
                    <div class="field"><label for="motiv">Motiv</label><select id="motiv" name="motiv">@foreach (\App\Modules\B2B\Models\Complaint::REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="field"><label for="linie">Lucrarea</label><select id="linie" name="linie"><option value="">Toată comanda</option>@foreach ($order->items as $item)<option value="{{ $item->id }}">{{ $item->patient_reference ?? '—' }} · {{ \Illuminate\Support\Str::limit($item->name, 60) }}</option>@endforeach</select></div>
                <div class="field"><label for="descriere">Descriere</label><textarea id="descriere" name="descriere" rows="4" required maxlength="3000"></textarea></div>
                <button class="btn" type="submit">Trimite sesizarea</button>
            </form>
            @foreach ($complaints as $c)<p class="small">{{ $c->number }} · {{ \App\Modules\B2B\Models\Complaint::REASONS[$c->reason] ?? $c->reason }} · {{ \App\Modules\B2B\Models\Complaint::STATUSES[$c->status] ?? $c->status }}</p>@endforeach
        </section>
        @endif
    </div>
    <aside class="summary">
        <h2>Rezumat</h2>
        <dl>
            <dt>Plasată</dt><dd>{{ $order->created_at->format('d.m.Y') }}</dd>
            @if ($order->purchase_order_ref)<dt>Referința dvs.</dt><dd>{{ $order->purchase_order_ref }}</dd>@endif
            <dt>Stare</dt><dd>{{ $order->commercialLabel() }}</dd>
            <dt>Plată</dt><dd>{{ $order->paymentMethodLabel() }} · {{ $order->paymentLabel() }}</dd>
            <dt>Livrare</dt><dd>{{ $fmt($order->shipping_total) }}</dd>
        </dl>
        <div class="summary__total"><span>Total</span><span>{{ $fmt($order->total) }}</span></div>
        @foreach ($order->shipments->whereNotNull('awb') as $sh)<p class="small">AWB {{ $sh->awb }}@if($sh->tracking_url) · <a href="{{ $sh->tracking_url }}" target="_blank" rel="noopener">urmărire</a>@endif</p>@endforeach
        @foreach ($documents as $d)<p class="small"><a href="/portal/documente/{{ $d->uuid }}/">{{ $d->title }}</a></p>@endforeach
        @if ($partner->can('order'))
            <form method="post" action="/portal/comenzi/{{ $order->uuid }}/din-nou/" style="margin-top: 16px">@csrf<button class="btn btn--block" type="submit">Comandă din nou aceleași lucrări</button></form>
        @endif
    </aside>
</div>
@endsection
