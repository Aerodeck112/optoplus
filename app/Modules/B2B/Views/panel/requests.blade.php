@extends('layouts.panou')
@section('title', 'Cereri de parteneriat')
@section('content')
    <x-panou.page-head title="Cereri de parteneriat" sub="Fiecare cerere se verifică manual; accesul la portal și prețurile B2B apar doar după aprobare." />
    <nav class="tabs">@foreach (['noua' => 'Noi', 'in_analiza' => 'În analiză', 'aprobata' => 'Aprobate', 'respinsa' => 'Respinse', 'toate' => 'Toate'] as $k => $l)<a href="?stare={{ $k }}" @if($status === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
    @forelse ($requests as $r)
        <x-panou.panel :title="$r->company_name.' · '.(\App\Modules\B2B\Models\Organization::KINDS[$r->kind] ?? $r->kind)">
            <dl class="kv">
                <dt>Contact</dt><dd>{{ $r->contact_name }} · {{ $r->email }} · {{ $r->phone }}</dd>
                <dt>CUI / oraș</dt><dd>{{ $r->vat_code ?? '—' }} · {{ $r->city ?? '—' }}</dd>
                <dt>Mesaj</dt><dd>{{ $r->message ?? '—' }}</dd>
                <dt>Primită</dt><dd>{{ $r->created_at->format('d.m.Y H:i') }} · {{ \App\Modules\B2B\Models\PartnershipRequest::STATUSES[$r->status] ?? $r->status }}@if($r->decision_reason) — {{ $r->decision_reason }}@endif</dd>
            </dl>
            @if (in_array($r->status, ['noua', 'in_analiza'], true))
                @can('b2b.approve')
                <div class="grid grid--2" style="margin-top:1rem">
                    <form method="post" action="{{ route('panou.b2b.requests.approve', $r->id) }}" class="stack">@csrf
                        <x-panou.field name="tip" type="select" label="Tip partener" :options="\App\Modules\B2B\Models\Organization::KINDS" :value="$r->kind" :placeholder="false" />
                        <x-panou.field name="lista" type="select" label="Lista de preț" :options="\App\Modules\Pricing\Models\PriceList::where('channel', 'b2b')->where('is_active', true)->pluck('name', 'id')->all()" placeholder="fără listă (prețuri B2C)" />
                        <x-panou.field name="cu_tva" type="checkbox" label="Afișează prețurile cu TVA" />
                        <button class="btn btn--primary" type="submit">Aprobă și trimite invitația</button>
                    </form>
                    <form method="post" action="{{ route('panou.b2b.requests.reject', $r->id) }}" class="stack">@csrf
                        <x-panou.field name="motiv" type="textarea" label="Motivul respingerii (intern)" required />
                        <button class="btn btn--danger" type="submit">Respinge</button>
                    </form>
                </div>
                @endcan
            @elseif ($r->organization_id)
                <p><a href="{{ route('panou.b2b.org', $r->organization_id) }}">Fișa partenerului</a></p>
            @endif
        </x-panou.panel>
    @empty
        <x-panou.panel><x-panou.empty title="Nicio cerere în această listă" /></x-panou.panel>
    @endforelse
    {{ $requests->links() }}
@endsection
