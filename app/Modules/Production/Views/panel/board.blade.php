@extends('layouts.panou')
@section('title', 'Producție')
@section('content')
    <x-panou.page-head title="Producție" sub="Lucrările de laborator pe etape. Nicio lucrare nu trece de verificare fără aprobarea specialistului optic.">
        <x-slot:actions>
            <a class="btn {{ ($filter['ale_mele'] ?? false) ? 'btn--primary' : '' }}" href="?ale_mele=1">Alocate mie</a>
            <a class="btn btn--ghost" href="{{ route('panou.production.index') }}">Toate</a>
        </x-slot:actions>
    </x-panou.page-head>
    <div class="board">
        @foreach ($stages as $stage)
            @continue(($filter['etapa'] ?? null) && $filter['etapa'] !== $stage->code)
            @php($list = $jobs[$stage->code] ?? collect())
            <section class="board__col" aria-labelledby="col-{{ $stage->code }}">
                <h2 id="col-{{ $stage->code }}">{{ $stage->name }} <span class="muted">{{ $list->count() }}</span></h2>
                @forelse ($list as $job)
                    <a class="board__card {{ $job->priority === 1 ? 'is-urgent' : '' }} {{ $job->due_on && $job->due_on->isPast() && $stage->code !== 'expediata' ? 'is-late' : '' }}" href="{{ route('panou.production.show', $job->id) }}">
                        <strong>{{ $job->number }}</strong>
                        <span class="small">{{ \Illuminate\Support\Str::limit($job->item->name, 70) }}</span>
                        <span class="small muted">@if($job->item->patient_reference)pacient {{ $job->item->patient_reference }} · @endif{{ $job->assignee?->name ?? 'nealocată' }}@if($job->due_on) · termen {{ $job->due_on->format('d.m') }}@endif</span>
                        @if ($job->order->commercial_status === 'noua')<x-panou.badge tone="warn">comandă neconfirmată</x-panou.badge>@endif
                        @if ($job->remake_of_id)<x-panou.badge tone="danger">refacere</x-panou.badge>@endif
                    </a>
                @empty
                    <p class="small muted">—</p>
                @endforelse
            </section>
        @endforeach
    </div>
@endsection
