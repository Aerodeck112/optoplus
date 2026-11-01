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
                <table class="table"><tbody>@foreach ($orders as $o)<tr><td>@can('orders.view')<a href="{{ route('panou.orders.show', $o->uuid) }}">{{ $o->number }}</a>@else{{ $o->number }}@endcan</td><td>{{ $o->created_at->format('d.m.Y') }}</td><td>{{ $o->commercialLabel() }}</td><td class="num">{{ \App\Core\Support\Money::format($o->total) }}</td></tr>@endforeach</tbody></table>
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
