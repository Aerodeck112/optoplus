@extends('layouts.panou')
@section('title', 'Recenzii')
@section('content')
    <x-panou.page-head title="Recenzii" sub="Recenziile lăsate de vizitatori pe produse și pe prima pagină (fără cont). Apar pe site doar după „Publică”. Respingeți doar spamul, limbajul jignitor, datele personale sau textele fără legătură cu produsul — nu recenziile negative (ar însemna să induceți clienții în eroare). „Cumpărare verificată” = emailul are o comandă cu acel produs." />
    <x-panou.panel :flush="true">
        <form class="filters" method="get">
            <x-panou.field name="stare" type="select" label="Stare" :options="collect(\App\Modules\Reviews\Models\Review::STATUSES)->map(fn ($l, $k) => $l.' ('.($counts[$k] ?? 0).')')->all()" :value="$status" placeholder="toate" />
            <x-panou.field name="tip" type="select" label="Despre" :options="['produse' => 'Produse', 'magazin' => 'Magazin (prima pagină)']" :value="$kind" placeholder="toate" />
            <button class="btn" type="submit">Filtrează</button>
        </form>
        @if ($items->isEmpty())
            <x-panou.empty title="Nicio recenzie">Recenziile trimise de pe site apar aici; primiți și o alertă pe email pentru fiecare.</x-panou.empty>
        @else
        <div class="table-wrap"><table class="table review-table">
            <thead><tr><th>Primită</th><th>Despre</th><th>Recenzia</th><th>Acțiuni</th></tr></thead>
            <tbody>@foreach ($items as $r)
                <tr>
                    <td class="num">{{ $r->created_at->format('d.m.Y H:i') }}<br>
                        <x-panou.badge :tone="['noua' => 'warn', 'publicata' => 'ok', 'respinsa' => 'danger'][$r->status] ?? null">{{ \App\Modules\Reviews\Models\Review::STATUSES[$r->status] ?? $r->status }}</x-panou.badge>
                        @if ($r->reject_reason)<br><span class="small muted">{{ $r->reject_reason }}</span>@endif
                    </td>
                    <td>@if ($r->product)<a href="{{ $r->product->url() }}" target="_blank" rel="noopener">{{ $r->product->name }}</a>@elseif($r->product_id)<span class="muted">produs șters</span>@else Magazin @endif</td>
                    <td>
                        <div><span class="review-stars" aria-label="{{ $r->rating }} din 5">{{ str_repeat('★', $r->rating) }}<span class="muted">{{ str_repeat('☆', 5 - $r->rating) }}</span></span>
                            @if ($r->verified)<x-panou.badge tone="ok">cumpărare verificată</x-panou.badge>@endif</div>
                        @if ($r->title)<strong>{{ $r->title }}</strong>@endif
                        <p class="small" style="margin:.2rem 0">{!! nl2br(e($r->body)) !!}</p>
                        <span class="small muted">{{ $r->name }}@if ($r->email) · {{ $r->email }}@endif · pe site: „{{ $r->displayName() }}” · limba {{ $r->locale }}</span>
                        @if ($r->reply)<div class="small" style="margin-top:.3rem;padding:.4rem .6rem;background:#f3f6f9;border-radius:6px"><strong>Răspunsul nostru:</strong> {{ $r->reply }}</div>@endif
                    </td>
                    <td>
                        <div class="cluster" style="gap:.3rem">
                            @if ($r->status !== 'publicata')<form method="post" action="{{ route('panou.reviews.update', $r->id) }}">@csrf<button class="btn btn--small btn--primary" name="actiune" value="publica">Publică</button></form>@endif
                            @if ($r->status === 'publicata')<form method="post" action="{{ route('panou.reviews.update', $r->id) }}">@csrf<button class="btn btn--small" name="actiune" value="noua">Ascunde</button></form>@endif
                            <form method="post" action="{{ route('panou.reviews.destroy', $r->id) }}" data-confirm="Ștergeți definitiv această recenzie?">@csrf<button class="btn btn--small btn--danger">Șterge</button></form>
                        </div>
                        @if ($r->status !== 'respinsa')
                        <details class="small"><summary>Respinge (cu motiv)</summary>
                            <form method="post" action="{{ route('panou.reviews.update', $r->id) }}" class="stack" style="gap:.3rem">@csrf<input type="hidden" name="actiune" value="respinge">
                                <select name="motiv" aria-label="Motivul"><option>Spam / reclamă</option><option>Limbaj jignitor</option><option>Conține date personale</option><option>Fără legătură cu produsul sau magazinul</option></select>
                                <button class="btn btn--small">Respinge</button></form>
                        </details>
                        @endif
                        <details class="small" @if(! $r->reply && $r->status === 'publicata' && $r->rating <= 3) open @endif><summary>{{ $r->reply ? 'Modifică răspunsul' : 'Răspunde public' }}</summary>
                            <form method="post" action="{{ route('panou.reviews.update', $r->id) }}" class="stack" style="gap:.3rem">@csrf<input type="hidden" name="actiune" value="raspuns">
                                <textarea name="raspuns" rows="3" maxlength="2000" aria-label="Răspunsul Optoplus">{{ $r->reply }}</textarea>
                                <button class="btn btn--small">Salvează răspunsul</button></form>
                        </details>
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @endif
        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
