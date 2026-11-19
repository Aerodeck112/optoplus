@extends('layouts.panou')
@section('title', 'Cerere: '.($lead->name ?? '#'.$lead->id))
@section('content')
    <x-panou.page-head :title="$lead->name ?? 'Cerere #'.$lead->id" :crumbs="['Cereri primite' => route('panou.crm.leads'), ($lead->name ?? '#'.$lead->id) => null]" :sub="(\App\Modules\Crm\Models\Lead::SOURCES[$lead->source] ?? $lead->source).' · '.$lead->created_at->format('d.m.Y H:i')" />
    <div class="grid grid--aside">
        <div class="stack">
            <x-panou.panel title="Detalii">
                <dl class="kv">
                    <dt>Telefon</dt><dd>@if($lead->phone)<a href="tel:{{ preg_replace('/[^+\d]/', '', $lead->phone) }}">{{ $lead->phone }}</a>@else — @endif</dd>
                    <dt>Email</dt><dd>@if($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@else — @endif</dd>
                    <dt>Cont client</dt><dd>{{ $lead->customer?->fullName() ?? 'fără cont' }}</dd>
                    <dt>Rezumat</dt><dd>{!! nl2br(e($lead->summary ?? '—')) !!}</dd>
                </dl>
                @if ($configuration)
                    <p class="small" style="margin-top:1rem"><strong>Configurația clientului:</strong> {{ $configuration->frameVariant?->displayName() ?? 'fără ramă' }} · {{ $configuration->offering ? $configuration->offering->product->name.' '.$configuration->offering->material->indexLabel() : 'lentilă nealeasă' }} · pasul {{ $configuration->step }}/5. Valorile rețetei sunt vizibile specialistului după plasarea comenzii, în fișa de lucru.</p>
                @endif
            </x-panou.panel>
            @if ($lead->prescription_id)
            {{-- 1.0.18: rețeta trimisă de client odată cu cererea (formularul de ajutor sau configuratorul). --}}
            <x-panou.panel title="Rețeta trimisă de client">
                @if (! $canRx)
                    <p class="muted">Clientul a trimis o rețetă, dar contul dumneavoastră nu are dreptul „Consultă rețetele și fișierele lor”. Un administrator îl poate acorda din Utilizatori → Editează → Drepturi.</p>
                @elseif (! $lead->prescription)
                    <p class="muted">Rețeta nu mai este disponibilă (a fost ștearsă la expirarea perioadei de păstrare).</p>
                @else
                    @php($p = $lead->prescription)
                    @php($f = fn ($v, $sign = true) => \App\Modules\Configurator\Support\EyeRx::format($v, $sign))
                    @php($mm = fn ($v) => $v === null ? '—' : number_format($v / 10, 1, ',', '').' mm')
                    @php($hasValues = $p->eye('od') || $p->eye('os'))
                    @if ($hasValues)
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AX</th><th>ADD</th><th>DP</th></tr></thead>
                            <tbody>
                            @foreach (['od' => 'OD', 'os' => 'OS'] as $eye => $label)
                                @php($e = $p->eye($eye))
                                @php($m = (array) $p->measurements)
                                <tr><th>{{ $label }}</th><td>{{ $e ? $f($e->sph) : '—' }}</td><td>{{ $e && $e->cyl ? $f($e->cyl) : '—' }}</td><td>{{ $e?->axis ?? '—' }}</td><td>{{ $e && $e->add ? $f($e->add, false) : '—' }}</td>
                                    <td>{{ $p->pd_mode === 'monocular' ? $mm($m['pd_'.$eye] ?? null) : ($eye === 'od' ? $mm($m['pd'] ?? null) : '') }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @else
                        <p class="small muted">Clientul nu a completat valorile; a trimis doar fișierul.</p>
                    @endif
                    <div class="cluster" style="margin-top:.5rem">
                        @forelse ($p->files as $file)
                            <a class="btn btn--small" href="{{ route('panou.prescriptions.file', $file->uuid) }}" target="_blank" rel="noopener"><x-panou.icon name="file" />Deschide rețeta ({{ $file->mime === 'application/pdf' ? 'PDF' : 'imagine' }})</a>
                        @empty
                            <span class="small muted">Fără fișier încărcat.</span>
                        @endforelse
                    </div>
                    <p class="small muted">@if($p->customer_unsure)Clientul a spus că nu înțelege rețeta. @endif Trimisă la {{ $p->created_at->format('d.m.Y H:i') }}@if($p->retention_until) · se păstrează până la {{ $p->retention_until->format('d.m.Y') }}@endif. Consultarea este înregistrată în jurnalul de audit.</p>
                @endif
            </x-panou.panel>
            @endif
            <x-panou.panel title="Note">
                @can('crm.manage')
                <form method="post" action="{{ route('panou.crm.lead.note', $lead->id) }}" class="stack">@csrf
                    <x-panou.field name="text" type="textarea" label="Notă (ce s-a discutat, ce urmează)" required />
                    <button class="btn" type="submit">Adaugă nota</button>
                </form>
                @endcan
                @foreach ($lead->notes as $n)<p class="small" style="margin-top:.75rem"><span class="muted">{{ $n->created_at->format('d.m.Y H:i') }} · {{ $authors[$n->created_by] ?? 'sistem' }}</span><br>{!! nl2br(e($n->body)) !!}</p>@endforeach
            </x-panou.panel>
            @if ($orders->isNotEmpty())
            <x-panou.panel title="Comenzi ale aceluiași client" :flush="true">
                <table class="table"><tbody>@foreach ($orders as $o)<tr><td>@can('orders.view')<a href="{{ route('panou.orders.show', $o->uuid) }}">{{ $o->number }}</a>@else{{ $o->number }}@endcan</td><td>{{ $o->created_at->format('d.m.Y') }}</td><td>{{ $o->commercialLabel() }}</td><td class="num">{{ \App\Core\Support\Money::format($o->total, true, $o->currency) }}</td></tr>@endforeach</tbody></table>
            </x-panou.panel>
            @endif
        </div>
        <div class="stack">
            @can('crm.manage')
            <x-panou.panel title="Stare">
                <form method="post" action="{{ route('panou.crm.lead.update', $lead->id) }}" class="stack">@csrf
                    <x-panou.field name="stare" type="select" label="Stare" :options="\App\Modules\Crm\Models\Lead::STATUSES" :value="$lead->status" :placeholder="false" />
                    <x-panou.field name="responsabil" type="select" label="Responsabil" :options="$users->all()" :value="$lead->owner_id" placeholder="nealocată" />
                    <button class="btn btn--primary" type="submit">Salvează</button>
                </form>
            </x-panou.panel>
            <x-panou.panel title="Sarcini">
                @foreach ($lead->tasks as $t)<p class="small">{{ $t->completed_at ? '✓' : '○' }} {{ $t->title }}<br><span class="muted">{{ $t->assignee?->name }}@if($t->due_at) · {{ $t->due_at->format('d.m.Y') }}@endif</span></p>@endforeach
                <form method="post" action="{{ route('panou.crm.lead.task', $lead->id) }}" class="stack">@csrf
                    <x-panou.field name="titlu" label="Sarcină nouă" required placeholder="ex. sună clientul pentru DP" />
                    <x-panou.field name="termen" type="date" label="Termen" />
                    <x-panou.field name="responsabil" type="select" label="Responsabil" :options="$users->all()" placeholder="eu" />
                    <button class="btn btn--small" type="submit">Adaugă</button>
                </form>
            </x-panou.panel>
            @endcan
        </div>
    </div>
@endsection
