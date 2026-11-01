@extends('layouts.panou')
@section('title', 'Sarcini')
@section('content')
    <x-panou.page-head title="Sarcini deschise">
        <x-slot:actions><a class="btn" href="?toate=1">Ale tuturor</a><a class="btn btn--ghost" href="?">Ale mele</a></x-slot:actions>
    </x-panou.page-head>
    <x-panou.panel :flush="true">
        <table class="table"><thead><tr><th>Sarcina</th><th>Legată de</th><th>Responsabil</th><th>Termen</th><th></th></tr></thead>
            <tbody>@forelse ($tasks as $t)<tr>
                <td>{{ $t->title }}</td>
                <td>@if($t->subject instanceof \App\Modules\Crm\Models\Lead)<a href="{{ route('panou.crm.lead', $t->subject->id) }}">{{ $t->subject->name ?? 'cerere #'.$t->subject->id }}</a>@else — @endif</td>
                <td>{{ $t->assignee?->name }}</td>
                <td>@if($t->due_at)<x-panou.badge :tone="$t->isOverdue() ? 'danger' : null">{{ $t->due_at->format('d.m.Y') }}</x-panou.badge>@endif</td>
                <td class="text-right"><form method="post" action="{{ route('panou.crm.tasks.complete', $t->id) }}">@csrf<button class="btn btn--small" type="submit">Făcută</button></form></td>
            </tr>@empty<tr><td colspan="5" class="muted">Nicio sarcină deschisă.</td></tr>@endforelse</tbody></table>
        <x-slot:footer>{{ $tasks->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
