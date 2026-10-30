@extends('layouts.panou')
@section('title', 'Comanda '.$order->number)
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
    <x-panou.page-head :title="'Comanda '.$order->number" :crumbs="['Comenzi' => route('panou.orders.index'), $order->number => null]"
        :sub="$order->created_at->format('d.m.Y H:i').' · '.strtoupper($order->channel).($order->organization ? ' · '.$order->organization->name : '')">
        <x-slot:actions>
            @can('orders.manage')
                @if ($order->commercial_status === 'noua')
                    <form method="post" action="{{ route('panou.orders.confirm', $order->uuid) }}">@csrf<button class="btn btn--primary" type="submit"><x-panou.icon name="check" />Confirmă comanda</button></form>
                @endif
            @endcan
        </x-slot:actions>
    </x-panou.page-head>

    <div class="metrics">
        <div class="metric"><span class="metric__label">Comercial</span><span class="metric__value" style="font-size:1.2rem">{{ $order->commercialLabel() }}</span></div>
        <div class="metric"><span class="metric__label">Plată ({{ $order->paymentMethodLabel() }})</span><span class="metric__value" style="font-size:1.2rem">{{ $order->paymentLabel() }}</span><span class="metric__help">încasat {{ $fmt($order->paid_total) }} din {{ $fmt($order->total) }}</span></div>
        <div class="metric"><span class="metric__label">Producție</span><span class="metric__value" style="font-size:1.2rem">{{ $stages[$order->production_status] ?? ($order->production_status === 'nu_se_aplica' ? 'nu se aplică' : $order->production_status) }}</span></div>
    </div>

    <div class="grid grid--aside">
        <div class="stack">
            <x-panou.panel title="Produse" :flush="true">
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Produs</th><th>Detalii</th><th>Cant.</th><th class="num">Valoare</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        @php($s = $item->snapshot)
                        <tr>
                            <td>{{ $item->name }}@if($item->patient_reference)<br><span class="small muted">pacient: {{ $item->patient_reference }}</span>@endif</td>
                            <td class="small">
                                @if ($item->kind === 'configuration')
                                    @if ($s['frame'] ?? null)Ramă: {{ $s['frame']['name'] }} ({{ $s['frame']['sku'] }})<br>@endif
                                    @if ($s['lens'] ?? null)Lentilă: {{ $s['lens']['product'] }}, {{ $s['lens']['material'] }} · {{ $s['lens']['offering_code'] ?? '' }}@if($s['lens']['offering_status'] === 'neconfirmat') <x-panou.badge tone="warn">ofertă neconfirmată</x-panou.badge>@endif<br>@endif
                                    @if (! empty($s['treatments']))Tratamente: {{ collect($s['treatments'])->map(fn ($t) => $t['name'].($t['option'] ? ' ('.$t['option'].')' : ''))->implode(', ') }}<br>@endif
                                    @foreach (($s['validation']['warnings'] ?? []) as $w)<span class="muted">⚠ {{ $w }}</span><br>@endforeach
                                    @if ($s['prescription']['customer_unsure'] ?? false)<x-panou.badge tone="warn">clientul nu înțelege rețeta</x-panou.badge>@endif
                                    @if ($s['prescription']['has_file'] ?? false)<x-panou.badge>rețetă încărcată</x-panou.badge>@endif
                                @else
                                    {{ $item->sku }}
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td class="num">{{ $fmt($item->line_total) }}@if($item->discount)<br><span class="small muted">reducere {{ $fmt($item->discount) }}</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-right">Livrare ({{ $order->shipping_method_code ?? '—' }})</td><td class="num">{{ $fmt($order->shipping_total) }}</td></tr>
                        <tr><td colspan="3" class="text-right">din care TVA</td><td class="num">{{ $fmt($order->vat_total) }}</td></tr>
                        <tr><td colspan="3" class="text-right"><strong>Total</strong></td><td class="num"><strong>{{ $fmt($order->total) }}</strong></td></tr>
                    </tfoot>
                </table></div>
                <x-slot:footer><span class="small muted">Prețuri calculate de motorul de preț {{ $order->pricing_version }}@if($order->coupon_code) · cupon {{ $order->coupon_code }}@endif. Valorile rețetei sunt în fișa de lucru (acces auditat).</span></x-slot:footer>
            </x-panou.panel>

            @if ($order->productionJobs->isNotEmpty())
                <x-panou.panel title="Lucrări de producție" :flush="true">
                    <table class="table"><tbody>
                        @foreach ($order->productionJobs as $job)
                            <tr><td>@can('production.view')<a href="{{ route('panou.production.show', $job->id) }}">{{ $job->number }}</a>@else{{ $job->number }}@endcan</td><td>{{ $stages[$job->stage] ?? $job->stage }}</td><td>{{ $job->assignee?->name ?? 'nealocată' }}</td><td>{{ $job->approved_at ? 'aprobată '.$job->approved_at->format('d.m H:i') : 'neaprobată' }}</td></tr>
                        @endforeach
                    </tbody></table>
                </x-panou.panel>
            @endif

            <x-panou.panel title="Plăți" :flush="true">
                @forelse ($order->payments as $p)
                    <div class="panel__body">
                        <strong>{{ \App\Modules\Payments\Models\Payment::STATUSES[$p->status] ?? $p->status }}</strong> · {{ $fmt($p->amount) }} · {{ $p->method }} {{ $p->provider ? '('.$p->provider.')' : '' }} @if($p->provider_reference)<span class="muted small">ref. {{ $p->provider_reference }}</span>@endif
                        @if ($p->confirmation_note)<br><span class="small">Confirmată manual de {{ $p->confirmer?->name }}: {{ $p->confirmation_note }}</span>@endif
                        @if ($p->failure_reason)<br><span class="small muted">{{ $p->failure_reason }}</span>@endif
                        @foreach ($p->refunds as $r)<br><span class="small">Rambursare {{ $fmt($r->amount) }} · {{ $r->status }} · {{ $r->reason }}</span>@endforeach
                        @if ($p->method !== 'card' && in_array($p->status, ['initiata', 'in_asteptare'], true))
                            @can('payments.confirm')
                                <form method="post" action="{{ route('panou.orders.payment.confirm', [$order->uuid, $p->id]) }}" class="cluster" style="margin-top:.5rem">@csrf
                                    <x-panou.field name="nota" label="Dovada încasării" placeholder="ex. extras BT din 12.11, poziția 4" required />
                                    <button class="btn btn--primary btn--small" type="submit">Confirmă încasarea</button></form>
                            @endcan
                        @endif
                        @if ($p->status === 'platita' && $p->refunded_amount < $p->amount)
                            @can('payments.refund')
                                <details style="margin-top:.5rem"><summary class="link-btn">Rambursează</summary>
                                <form method="post" action="{{ route('panou.orders.payment.refund', [$order->uuid, $p->id]) }}" class="cluster">@csrf
                                    <x-panou.field name="suma" label="Suma (lei)" :value="number_format(($p->amount - $p->refunded_amount) / 100, 2, ',', '')" required />
                                    <x-panou.field name="motiv" label="Motiv" required />
                                    <button class="btn btn--danger btn--small" type="submit">Rambursează</button></form></details>
                            @endcan
                        @endif
                    </div>
                @empty
                    <div class="panel__body muted">Nicio plată inițiată.</div>
                @endforelse
            </x-panou.panel>

            <x-panou.panel title="Istoric">
                <ul class="code-list">
                    @foreach ($order->events as $e)
                        <li><span class="muted small">{{ $e->created_at->format('d.m.Y H:i') }}</span> · <strong>{{ $e->axis }}</strong>@if($e->to_status) {{ $e->from_status ?? '∅' }} → {{ $e->to_status }}@endif @if($e->message) — {{ $e->message }}@endif <span class="muted small">({{ $e->user?->name ?? $e->actor ?? 'sistem' }}{{ $e->visible_to_customer ? ', vizibil clientului' : '' }})</span></li>
                    @endforeach
                </ul>
                @can('orders.manage')
                <form method="post" action="{{ route('panou.orders.note', $order->uuid) }}" class="form-grid" style="margin-top:1rem">@csrf
                    <x-panou.field name="mesaj" type="textarea" label="Notă sau mesaj" :full="true" required />
                    <x-panou.field name="vizibil" type="checkbox" label="Trimite clientului (email + pagina comenzii)" />
                    <div class="form-actions"><button class="btn" type="submit">Salvează</button></div>
                </form>
                @endcan
            </x-panou.panel>
        </div>

        <div class="stack">
            <x-panou.panel title="Client">
                <p>{{ $order->billing_address['company'] ?? $order->billing_address['name'] ?? '—' }}@if($order->billing_address['tax_id'] ?? null)<br>CUI {{ $order->billing_address['tax_id'] }}@endif</p>
                <p class="small">{{ $order->email }}<br>{{ $order->phone }}</p>
                @if ($order->shipping_address)<p class="small"><strong>Livrare:</strong> {{ $order->shipping_address['name'] ?? '' }}, {{ $order->shipping_address['street'] ?? '' }}, {{ $order->shipping_address['city'] ?? '' }} {{ $order->shipping_address['county'] ?? '' }}</p>@endif
                @if ($order->purchase_order_ref)<p class="small">Ref. partener: {{ $order->purchase_order_ref }}</p>@endif
                @if ($order->customer_note)<p class="small"><strong>Observații:</strong> {{ $order->customer_note }}</p>@endif
            </x-panou.panel>

            <x-panou.panel title="Livrare">
                @foreach ($order->shipments as $sh)
                    <p class="small">{{ $sh->carrier }} · AWB {{ $sh->awb ?? '—' }} · <strong>{{ \App\Modules\Orders\Models\Shipment::STATUSES[$sh->status] ?? $sh->status }}</strong></p>
                    @can('orders.manage')
                    <form method="post" action="{{ route('panou.orders.shipment.update', [$order->uuid, $sh->id]) }}" class="cluster">@csrf
                        <x-panou.field name="stare" type="select" label="Stare" :options="\App\Modules\Orders\Models\Shipment::STATUSES" :value="$sh->status" :placeholder="false" />
                        <button class="btn btn--small" type="submit">Actualizează</button></form>
                    @endcan
                @endforeach
                @can('orders.manage')
                @if ($order->commercial_status !== 'anulata')
                <details @if($order->shipments->isEmpty()) open @endif><summary class="link-btn">Înregistrează o expediere</summary>
                <form method="post" action="{{ route('panou.orders.shipment', $order->uuid) }}" class="stack">@csrf
                    <x-panou.field name="curier" label="Curier" value="manual" required hint="Adaptorul de curier nu este configurat: AWB-ul se generează în aplicația curierului și se copiază aici." />
                    <x-panou.field name="awb" label="AWB" />
                    <x-panou.field name="urmarire" type="url" label="Link de urmărire (https)" />
                    <x-panou.field name="stare" type="select" label="Stare" :options="\App\Modules\Orders\Models\Shipment::STATUSES" value="predata" :placeholder="false" />
                    <button class="btn" type="submit">Salvează</button>
                </form></details>
                @endif
                @endcan
            </x-panou.panel>

            <x-panou.panel title="Facturare">
                @forelse ($order->invoices as $inv)
                    <p class="small">{{ ucfirst($inv->kind) }} {{ $inv->series }} {{ $inv->number }} · {{ $inv->provider }} @if($inv->document_url)· <a href="{{ $inv->document_url }}" target="_blank" rel="noopener">document</a>@endif</p>
                @empty
                    <p class="small muted">Nicio factură înregistrată. Facturile se emit în sistemul de facturare (integrarea nu este configurată); aici se trece referința.</p>
                @endforelse
                @can('orders.manage')
                <details><summary class="link-btn">Adaugă referința facturii</summary>
                <form method="post" action="{{ route('panou.orders.invoice', $order->uuid) }}" class="stack">@csrf
                    <x-panou.field name="sistem" label="Sistem de facturare" value="manual" required />
                    <x-panou.field name="tip" type="select" label="Tip" :options="['factura' => 'Factură', 'storno' => 'Storno', 'proforma' => 'Proformă']" value="factura" :placeholder="false" />
                    <x-panou.field name="serie" label="Serie" />
                    <x-panou.field name="numar" label="Număr" required />
                    <x-panou.field name="link" type="url" label="Link către document (https)" />
                    <button class="btn" type="submit">Salvează</button>
                </form></details>
                @endcan
            </x-panou.panel>

            @can('orders.cancel')
            @if (in_array($order->commercial_status, ['noua', 'confirmata'], true))
            <x-panou.panel title="Anulare">
                <form method="post" action="{{ route('panou.orders.cancel', $order->uuid) }}" class="stack">@csrf
                    <x-panou.field name="motiv" label="Motivul (îl vede clientul)" required />
                    <button class="btn btn--danger" type="submit">Anulează comanda</button>
                </form>
            </x-panou.panel>
            @endif
            @endcan
        </div>
    </div>
@endsection
