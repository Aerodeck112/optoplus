@extends('layouts.panou')
@section('title', 'Lucrarea '.$job->number)
@php($s = $job->item->snapshot)
@php($p = $job->item->prescription)
@php($f = fn ($v, $sign = true) => \App\Modules\Configurator\Support\EyeRx::format($v, $sign))
@php($mm = fn ($v) => $v === null ? '—' : number_format($v / 10, 1, ',', '').' mm')
@section('content')
    <x-panou.page-head :title="'Lucrarea '.$job->number" :crumbs="['Producție' => route('panou.production.index'), $job->number => null]"
        :sub="'Comanda '.$job->order->number.($job->order->organization ? ' · '.$job->order->organization->name : '').($job->item->patient_reference ? ' · pacient '.$job->item->patient_reference : '')">
        <x-slot:actions>
            @can('orders.view')<a class="btn" href="{{ route('panou.orders.show', $job->order->uuid) }}">Comanda</a>@endcan
        </x-slot:actions>
    </x-panou.page-head>
    @if ($job->order->commercial_status !== 'confirmata')
        <div class="alert alert--warn">Comanda nu este confirmată ({{ $job->order->commercialLabel() }}, plată: {{ $job->order->paymentLabel() }}). Lucrarea se poate verifica, dar nu poate intra în producție.</div>
    @endif
    @if ($job->remakeOf)<div class="alert alert--info">Refacere pentru {{ $job->remakeOf->number }}.</div>@endif

    <div class="grid grid--aside">
        <div class="stack">
            <x-panou.panel title="Fișa de lucru">
                <dl class="kv">
                    <dt>Lentila</dt><dd>{{ $s['lens']['product'] ?? '—' }}, {{ $s['lens']['material'] ?? '' }} <span class="muted">({{ $s['lens']['offering_code'] ?? '' }}, formă: {{ $s['lens']['supply_form'] ?? '—' }}, ⌀ {{ $s['lens']['diameter_mm'] ?? '—' }})</span>@if(($s['lens']['offering_status'] ?? '') === 'neconfirmat') <x-panou.badge tone="warn">ofertă neconfirmată de laborator</x-panou.badge>@endif</dd>
                    <dt>Tratamente</dt><dd>{{ collect($s['treatments'] ?? [])->map(fn ($t) => $t['name'].($t['option'] ? ' ('.$t['option'].')' : ''))->implode(', ') ?: 'fără' }}</dd>
                    <dt>Ramă</dt><dd>@if($s['frame'] ?? null){{ $s['frame']['name'] }} · {{ $s['frame']['sku'] }}@if($s['frame']['spec']) · {{ $s['frame']['spec']['lens_width_mm'] }}□{{ $s['frame']['spec']['bridge_mm'] }}-{{ $s['frame']['spec']['temple_mm'] }}, {{ $s['frame']['spec']['rim_type'] ?? '' }}@endif @else fără ramă (doar lentile) @endif</dd>
                    <dt>Utilizare</dt><dd>{{ \App\Modules\Configurator\Support\ConfigurationInput::USAGES[$s['usage'] ?? ''] ?? '—' }} · ochi: {{ $s['eyes'] ?? 'ambii' }} · montaj: {{ $job->requires_mounting ? 'da' : 'nu' }}</dd>
                </dl>
                @if (! empty($s['validation']['review_reasons']) || ! empty($s['validation']['warnings']))
                    <div class="alert alert--info" style="margin-top:1rem"><strong>De verificat:</strong><ul>@foreach (array_merge($s['validation']['review_reasons'] ?? [], $s['validation']['warnings'] ?? []) as $r)<li>{{ $r }}</li>@endforeach</ul></div>
                @endif
            </x-panou.panel>

            <x-panou.panel title="Rețeta">
                @if (! $canRx)
                    <p class="muted">Nu aveți permisiunea de a vedea rețetele.</p>
                @elseif (! $p)
                    <p class="muted">Lucrarea nu are rețetă atașată.</p>
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AX</th><th>ADD</th><th>DP</th><th>Înălțime</th></tr></thead>
                        <tbody>
                        @foreach (['od' => 'OD', 'os' => 'OS'] as $eye => $label)
                            @php($e = $p->eye($eye))
                            @php($m = (array) $p->measurements)
                            <tr><th>{{ $label }}</th><td>{{ $e ? $f($e->sph) : '—' }}</td><td>{{ $e && $e->cyl ? $f($e->cyl) : '—' }}</td><td>{{ $e?->axis ?? '—' }}</td><td>{{ $e && $e->add ? $f($e->add, false) : '—' }}</td>
                                <td>{{ $p->pd_mode === 'monocular' ? $mm($m['pd_'.$eye] ?? null) : ($eye === 'od' ? $mm($m['pd'] ?? null).' (binocular)' : '') }}</td><td>{{ $mm($m['height_'.$eye] ?? null) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <p class="small muted">Sursa: {{ $p->source }} · confirmată de client {{ $p->confirmed_by_customer_at?->format('d.m.Y H:i') ?? '—' }}@if(($p->measurements['by_store'] ?? false)) · <strong>clientul a cerut măsurători făcute de specialist</strong>@endif @if($p->customer_unsure) · <strong>clientul nu înțelege rețeta</strong>@endif</p>
                    @if ($p->verified_at)<p class="small">Verificată de {{ $p->verifier?->name }} la {{ $p->verified_at->format('d.m.Y H:i') }}.</p>@endif
                    @foreach ($p->files as $file)
                        <a class="btn btn--small" href="{{ route('panou.prescriptions.file', $file->uuid) }}" target="_blank" rel="noopener"><x-panou.icon name="file" />Fișierul rețetei ({{ $file->mime === 'application/pdf' ? 'PDF' : 'imagine' }})</a>
                    @endforeach
                    <p class="small muted">Comparați valorile introduse de client cu fișierul încărcat. Consultarea este înregistrată în jurnalul de audit.</p>
                @endif
            </x-panou.panel>

            <x-panou.panel title="Jurnal">
                <ul class="code-list">
                    @foreach ($job->events as $e)
                        <li><span class="small muted">{{ $e->created_at->format('d.m.Y H:i') }}</span> · {{ $e->change }}@if($e->to_stage) {{ $e->from_stage ?? '∅' }} → {{ $e->to_stage }}@endif @if($e->note) — {{ $e->note }}@endif <span class="small muted">({{ $e->user?->name ?? 'sistem' }})</span></li>
                    @endforeach
                </ul>
            </x-panou.panel>
        </div>

        <div class="stack">
            @can('production.manage')
            <x-panou.panel title="Etapa">
                <p>Acum: <strong>{{ $stages->firstWhere('code', $job->stage)?->name ?? $job->stage }}</strong>@if($job->approved_at)<br><span class="small">aprobată de {{ $job->approver?->name }} la {{ $job->approved_at->format('d.m.Y H:i') }}</span>@endif</p>
                <form method="post" action="{{ route('panou.production.move', $job->id) }}" class="stack">@csrf
                    <input type="hidden" name="versiune" value="{{ $job->version }}">
                    <x-panou.field name="etapa" type="select" label="Mută în etapa" :options="$stages->reject(fn ($s) => $s->code === $job->stage)->pluck('name', 'code')->all()" required />
                    @can('production.approve')<x-panou.field name="verificat" type="checkbox" label="Am verificat rețeta, lentila și măsurătorile (obligatoriu pentru aprobare)" />@endcan
                    <x-panou.field name="nota" type="textarea" label="Notă" />
                    <button class="btn btn--primary" type="submit">Mută</button>
                </form>
            </x-panou.panel>
            <x-panou.panel title="Alocare">
                <form method="post" action="{{ route('panou.production.assign', $job->id) }}" class="stack">@csrf
                    <x-panou.field name="responsabil" type="select" label="Responsabil" :options="$users->all()" :value="$job->assignee_id" placeholder="nealocată" />
                    <x-panou.field name="termen" type="date" label="Termen" :value="$job->due_on?->format('Y-m-d')" />
                    <x-panou.field name="lot" label="Lot / referință trasabilitate" :value="$job->lot_reference" />
                    <x-panou.field name="prioritate" type="select" label="Prioritate" :options="[1 => 'Urgent', 2 => 'Normal', 3 => 'Scăzut']" :value="$job->priority" :placeholder="false" />
                    <button class="btn" type="submit">Salvează</button>
                </form>
            </x-panou.panel>
            @endcan
            @can('production.remake')
            <x-panou.panel title="Refacere">
                @foreach ($remakes as $r)<p class="small">{{ \App\Modules\Production\Models\Remake::REASONS[$r->reason] ?? $r->reason }} · cost: {{ $r->cost_borne_by }}</p>@endforeach
                <details><summary class="link-btn">Deschide o refacere</summary>
                <form method="post" action="{{ route('panou.production.remake', $job->id) }}" class="stack">@csrf
                    <x-panou.field name="motiv" type="select" label="Motiv" :options="\App\Modules\Production\Models\Remake::REASONS" required />
                    <x-panou.field name="cost" type="select" label="Costul îl suportă" :options="['optoplus' => 'Optoplus', 'client' => 'Clientul', 'partener' => 'Partenerul', 'de_stabilit' => 'De stabilit']" value="de_stabilit" :placeholder="false" />
                    <x-panou.field name="detalii" type="textarea" label="Detalii" />
                    <button class="btn btn--danger" type="submit">Deschide refacerea</button>
                </form></details>
            </x-panou.panel>
            @endcan
        </div>
    </div>
@endsection
