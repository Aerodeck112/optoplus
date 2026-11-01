@extends('layouts.panou')
@section('title', 'Parteneri')
@section('content')
    <x-panou.page-head title="Parteneri B2B" sub="Clinici, cabinete și magazine de optică aprobate." />
    <x-panou.panel :flush="true">
        <form class="filters" method="get" role="search"><x-panou.field name="q" type="search" label="Nume sau CUI" :value="$term" /><button class="btn" type="submit">Caută</button></form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Partener</th><th>Tip</th><th>Stare</th><th>Utilizatori</th><th>Plată la termen</th></tr></thead>
            <tbody>@foreach ($orgs as $o)<tr><td><a class="row-link" href="{{ route('panou.b2b.org', $o->id) }}">{{ $o->name }}</a><br><span class="small muted">{{ $o->vat_code }}</span></td><td>{{ \App\Modules\B2B\Models\Organization::KINDS[$o->kind] ?? $o->kind }}</td><td><x-panou.badge :tone="$o->status === 'aprobata' ? 'ok' : 'warn'">{{ $o->status }}</x-panou.badge></td><td>{{ $o->members_count }}</td><td>{{ $o->hasApprovedCredit() ? $o->payment_terms_days.' zile' : '—' }}</td></tr>@endforeach</tbody>
        </table></div>
        <x-slot:footer>{{ $orgs->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
