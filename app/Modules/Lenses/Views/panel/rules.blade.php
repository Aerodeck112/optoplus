@extends('layouts.panou')
@section('title', 'Reguli de compatibilitate')
@section('content')
    <x-panou.page-head title="Reguli de compatibilitate" sub="Blochează, cer verificare, avertizează sau cer o măsurătoare. Se aplică pe server, în configurator, coș, comanda rapidă B2B și asistent.">
        <x-slot:actions>@can('lenses.edit')<a class="btn btn--primary" href="{{ route('panou.lenses.rules.create') }}"><x-panou.icon name="plus" />Regulă nouă</a>@endcan</x-slot:actions>
    </x-panou.page-head>
    <x-panou.panel :flush="true">
        <table class="table"><thead><tr><th>Regula</th><th>Efect</th><th>Mesajul pentru client</th><th>Prioritate</th><th>Activă</th></tr></thead>
            <tbody>@forelse ($rules as $r)<tr>
                <td><a class="row-link" href="{{ route('panou.lenses.rules.edit', $r->id) }}">{{ $r->name }}</a>@if($r->source)<br><span class="small muted">{{ $r->source }}</span>@endif</td>
                <td><x-panou.badge :tone="['block' => 'danger', 'require_review' => 'info', 'warn' => 'warn', 'require_field' => 'accent'][$r->effect] ?? null">{{ ['block' => 'blochează', 'require_review' => 'cere verificare', 'warn' => 'avertizează', 'require_field' => 'cere câmp'][$r->effect] ?? $r->effect }}</x-panou.badge></td>
                <td class="small">{{ $r->message_customer }}</td><td>{{ $r->priority }}</td><td>{{ $r->is_active ? 'da' : 'nu' }}</td>
            </tr>@empty<tr><td colspan="5" class="muted">Nicio regulă.</td></tr>@endforelse</tbody></table>
    </x-panou.panel>
@endsection
