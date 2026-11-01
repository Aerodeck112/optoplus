@extends('layouts.panou')
@section('title', 'Sesizări și refaceri')
@section('content')
    <x-panou.page-head title="Sesizări, reclamații și refaceri" sub="Refacerea efectivă se deschide din fișa lucrării de producție." />
    <nav class="tabs">@foreach (['noua' => 'Noi', 'in_analiza' => 'În analiză', 'acceptata' => 'Acceptate', 'rezolvata' => 'Rezolvate', 'toate' => 'Toate'] as $k => $l)<a href="?stare={{ $k }}" @if($status === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
    @forelse ($complaints as $c)
        <x-panou.panel :title="$c->number.' · '.(\App\Modules\B2B\Models\Complaint::REASONS[$c->reason] ?? $c->reason).' · '.$c->kind">
            <p>Comanda @can('orders.view')<a href="{{ route('panou.orders.show', $c->order->uuid) }}">{{ $c->order->number }}</a>@else{{ $c->order->number }}@endcan @if($c->item) · {{ $c->item->name }}@if($c->item->patient_reference) (pacient {{ $c->item->patient_reference }})@endif @endif</p>
            <p>{!! nl2br(e($c->description)) !!}</p>
            @can('b2b.complaints')
            <form method="post" action="{{ route('panou.b2b.complaints.update', $c->id) }}" class="form-grid">@csrf
                <x-panou.field name="stare" type="select" label="Stare" :options="\App\Modules\B2B\Models\Complaint::STATUSES" :value="$c->status" :placeholder="false" />
                <x-panou.field name="rezolutie" type="textarea" label="Rezoluție" :value="$c->resolution" :full="true" />
                <div class="form-actions"><button class="btn" type="submit">Salvează</button></div>
            </form>
            @endcan
        </x-panou.panel>
    @empty
        <x-panou.panel><x-panou.empty title="Nicio sesizare în această listă" /></x-panou.panel>
    @endforelse
    {{ $complaints->links() }}
@endsection
