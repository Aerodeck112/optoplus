@extends('layouts.panou')
@section('title', 'Pagini și ghiduri')
@section('content')
    <x-panou.page-head title="Pagini și ghiduri" sub="Fiecare salvare creează o ciornă; pagina publică se schimbă doar la „Publică”. Istoricul permite revenirea la orice versiune." />
    <nav class="tabs">@foreach (['' => 'Toate', 'home' => 'Prima pagină', 'page' => 'Pagini', 'guide' => 'Ghiduri', 'policy' => 'Juridice'] as $k => $l)<a href="?tip={{ $k }}" @if((string) $kind === $k) aria-current="page" @endif>{{ $l }}</a>@endforeach</nav>
    <x-panou.panel :flush="true">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Pagina</th><th>Adresa</th><th>Stare</th><th>Ultima modificare</th></tr></thead>
            <tbody>@foreach ($pages as $p)<tr>
                <td><a class="row-link" href="{{ route('panou.cms.pages.edit', $p->id) }}">{{ $p->title }}</a></td>
                <td><a href="{{ $p->path }}" target="_blank" rel="noopener" class="small">{{ $p->path }}</a></td>
                <td>
                    <x-panou.badge :tone="$p->status === 'published' ? 'ok' : 'warn'">{{ $p->status === 'published' ? 'publicată' : 'nepublicată' }}</x-panou.badge>
                    @if ($p->hasUnpublishedChanges())<x-panou.badge tone="info">ciornă nepublicată</x-panou.badge>@endif
                    @if ($p->needs_legal_review)<x-panou.badge tone="danger">verificare juridică</x-panou.badge>@endif
                </td>
                <td class="small">{{ $p->updated_at?->format('d.m.Y H:i') }}@if($p->currentVersion?->note)<br><span class="muted">{{ $p->currentVersion->note }}</span>@endif</td>
            </tr>@endforeach</tbody>
        </table></div>
    </x-panou.panel>
    <x-panou.panel title="Pagină sau ghid nou">
        <form method="post" action="{{ route('panou.cms.pages.create') }}" class="form-grid">@csrf
            <x-panou.field name="titlu" label="Titlu" required />
            <x-panou.field name="tip" type="select" label="Tip" :options="['page' => 'Pagină', 'guide' => 'Ghid']" value="page" :placeholder="false" />
            <x-panou.field name="cale" label="Adresa (opțional, ex. /despre-lentile/)" />
            <div class="form-actions"><button class="btn btn--primary" type="submit">Creează ciorna</button></div>
        </form>
    </x-panou.panel>
@endsection
