@extends('layouts.panou')
@section('title', 'Traduceri')
@php($statuses = \App\Modules\I18n\Http\Controllers\TranslationController::STATUSES)
@section('content')
    <x-panou.page-head title="Traduceri" sub="Siteul în engleză (/en/) și maghiară (/hu/). Fiecare text românesc are traducerea lui; cifrele apar ca {0}, {1}…, iar {t} înseamnă un nume păstrat neschimbat (ex. modelul ramei). Textele juridice rămân în română.">
        <x-slot:actions>
            <form method="post" action="{{ route('panou.i18n.scan') }}">@csrf<button class="btn" type="submit"><x-panou.icon name="refresh" />Scanează siteul</button></form>
        </x-slot:actions>
    </x-panou.page-head>
    <nav class="tabs">
        @foreach (\App\Modules\I18n\Support\Locales::translated() as $l)
            <a href="?limba={{ $l }}" @if($locale === $l) aria-current="page" @endif>{{ \App\Modules\I18n\Support\Locales::ALL[$l] }}</a>
        @endforeach
    </nav>
    <x-panou.panel>
        <form method="get" class="filters-row" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: end">
            <input type="hidden" name="limba" value="{{ $locale }}">
            <x-panou.field name="stare" type="select" label="Stare" :options="['' => 'Toate'] + $statuses" :value="$status" :placeholder="false" />
            <x-panou.field name="q" label="Caută (română sau traducere)" :value="$q" />
            <button class="btn" type="submit">Filtrează</button>
        </form>
        <p class="small muted" style="margin-top: 8px">
            @foreach ($statuses as $k => $label){{ $label }}: <strong>{{ $counts[$k] ?? 0 }}</strong>@if(! $loop->last) · @endif @endforeach
        </p>
    </x-panou.panel>
    <x-panou.panel :flush="true">
        <div class="table-wrap"><table class="table">
            <thead><tr><th style="width: 40%">Română</th><th>{{ \App\Modules\I18n\Support\Locales::ALL[$locale] }}</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->source }}<br><x-panou.badge :tone="match ($r->status) { 'verificat' => 'ok', 'lipsa' => 'danger', 'automat' => 'warn', default => 'info' }">{{ $statuses[$r->status] ?? $r->status }}</x-panou.badge>@if($r->context) <span class="small muted">{{ $r->context }}</span>@endif</td>
                    <td>
                        <form method="post" action="{{ route('panou.i18n.update', $r->id) }}" style="display: grid; gap: 6px">@csrf
                            <textarea name="traducere" rows="{{ min(6, max(1, (int) ceil(mb_strlen($r->source) / 70))) }}" lang="{{ $locale }}" aria-label="Traducerea în {{ \App\Modules\I18n\Support\Locales::ALL[$locale] }}">{{ $r->target }}</textarea>
                            <span style="display: flex; gap: 12px; align-items: center">
                                <label class="small"><input type="checkbox" name="verificat" value="1" @checked($r->status === 'verificat')> verificată</label>
                                <button class="btn btn--small" type="submit">Salvează</button>
                            </span>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">Niciun text.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
