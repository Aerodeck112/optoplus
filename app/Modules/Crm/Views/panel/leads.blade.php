@extends('layouts.panou')
@section('title', 'Cereri primite')
@section('content')
    <x-panou.page-head title="Cereri primite" sub="Formulare, configurator, asistent și apeluri. Rezumatele nu conțin valori din rețetă.">
        <x-slot:actions>@can('crm.manage')<a class="btn btn--primary" href="#nou"><x-panou.icon name="plus" />Cerere telefonică</a>@endcan</x-slot:actions>
    </x-panou.page-head>
    <nav class="tabs">@foreach (['deschise' => 'Deschise', 'noua' => 'Noi', 'convertit' => 'Convertite', 'pierdut' => 'Pierdute', 'toate' => 'Toate'] as $k => $l)<a href="?stare={{ $k }}" @if($status === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
    <x-panou.panel :flush="true">
        <form class="filters" method="get"><input type="hidden" name="stare" value="{{ $status }}">
            <x-panou.field name="q" type="search" label="Nume, email, telefon" :value="$filters['q'] ?? ''" />
            <x-panou.field name="sursa" type="select" label="Sursa" :options="\App\Modules\Crm\Models\Lead::SOURCES" :value="$filters['sursa'] ?? ''" placeholder="toate" />
            <x-panou.field name="ale_mele" type="checkbox" label="Doar ale mele" :value="$filters['ale_mele'] ?? false" />
            <button class="btn" type="submit">Filtrează</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Cine</th><th>Sursa</th><th>Rezumat</th><th>Stare</th><th>Responsabil</th><th>Primită</th></tr></thead>
            <tbody>@forelse ($leads as $l)<tr>
                <td><a class="row-link" href="{{ route('panou.crm.lead', $l->id) }}">{{ $l->name ?? '—' }}</a><br><span class="small muted">{{ $l->phone }} {{ $l->email }}</span></td>
                <td>{{ \App\Modules\Crm\Models\Lead::SOURCES[$l->source] ?? $l->source }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->summary, 90) }}</td>
                <td><x-panou.badge :tone="$l->status === 'noua' ? 'warn' : null">{{ \App\Modules\Crm\Models\Lead::STATUSES[$l->status] ?? $l->status }}</x-panou.badge></td>
                <td>{{ $l->owner?->name ?? '—' }}</td>
                <td>{{ $l->created_at->format('d.m.Y H:i') }}</td></tr>
            @empty<tr><td colspan="6" class="muted">Nicio cerere.</td></tr>@endforelse</tbody>
        </table></div>
        <x-slot:footer>{{ $leads->links() }}</x-slot:footer>
    </x-panou.panel>
    @can('crm.manage')
    <x-panou.panel title="Cerere telefonică sau adăugată manual" id="nou">
        <form method="post" action="{{ route('panou.crm.leads.store') }}" class="form-grid">@csrf
            <x-panou.field name="nume" label="Nume" required />
            <x-panou.field name="telefon" type="tel" label="Telefon" />
            <x-panou.field name="email" type="email" label="Email" />
            <x-panou.field name="sursa" type="select" label="Sursa" :options="['telefon' => 'Telefon', 'manual' => 'Manual']" value="telefon" :placeholder="false" />
            <x-panou.field name="rezumat" type="textarea" label="Ce dorește (fără valori din rețetă)" :full="true" />
            <div class="form-actions"><button class="btn btn--primary" type="submit">Adaugă</button></div>
        </form>
    </x-panou.panel>
    @endcan
@endsection
