@extends('b2b::portal.shell')
@section('title', 'Documente')
@section('heading', 'Documente')
@section('body')
<div class="box">
    @if ($documents->isEmpty())<p class="muted">Nu există documente încărcate pentru organizația dumneavoastră.</p>@else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Document</th><th>Tip</th><th>Data</th></tr></thead>
        <tbody>@foreach ($documents as $d)<tr><td><a href="/portal/documente/{{ $d->uuid }}/">{{ $d->title }}</a></td><td>{{ \App\Modules\B2B\Models\OrganizationDocument::KINDS[$d->kind] ?? $d->kind }}</td><td>{{ $d->created_at->format('d.m.Y') }}</td></tr>@endforeach</tbody>
    </table></div>
    {{ $documents->links('storefront::partials.pagination') }}
    @endif
</div>
<p class="small muted">Facturile fiscale sunt emise de sistemul de facturare al Optoplus; aici găsiți copiile încărcate pentru organizația dumneavoastră.</p>
@endsection
