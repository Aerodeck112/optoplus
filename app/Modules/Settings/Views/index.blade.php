@extends('layouts.panou')
@section('title', 'Setări — '.$def['label'])
@section('content')
    <x-panou.page-head title="Setări" :sub="$def['intro'] ?? null" />
    <nav class="tabs" aria-label="Secțiuni">
        @foreach ($sections as $key => $s)<a href="{{ route('panou.settings', $key) }}" @if($key === $section) aria-current="page" @endif>{{ $s['label'] }}</a>@endforeach
    </nav>
    @php($groups = $def['groups'] ?? [$def['label'] => $def['fields']])
    <form method="post" action="{{ route('panou.settings.save', $section) }}" class="stack" autocomplete="off">@csrf
        @foreach ($groups as $title => $fields)
            <x-panou.panel :title="$title">
                @if ($section === 'plati' && str_contains($title, 'iPay'))
                    @if ($btStatus)<div class="alert alert--warn">{{ $btStatus }}</div>@else<div class="alert alert--ok">Datele BT iPay sunt completate. Testați conexiunea înainte de a activa plata.</div>@endif
                    <p class="small muted">Adresa de revenire (returnUrl) se trimite automat. Adresa de notificare (callback) de comunicat la BT: <code>{{ url('/webhook/plati/btipay') }}</code>. Plata se confirmă numai după interogarea stării la BT (server-la-server), niciodată din revenirea clientului pe site.</p>
                @endif
                @if ($section === 'firma')
                    <div class="alert alert--info">Conform ANAF (interogare din {{ now()->format('d.m.Y') }}, serviciul public „PlatitorTvaRest”), OFA SRL nu figurează înregistrată în scopuri de TVA. Verificați cu contabilul: dacă firma nu este plătitoare de TVA, debifați „Firma este înregistrată în scopuri de TVA”; prețurile rămân aceleași, dar nu se mai afișează „din care TVA”.</div>
                @endif
                <div class="form-grid">
                @foreach ($fields as $key => $f)
                    @php([$label, $type] = $f)
                    @php($name = str_replace('.', '__', $key))
                    @php($v = $values[$key] ?? null)
                    @switch($type)
                        @case('bool')<x-panou.field :name="$name" type="checkbox" :label="$label" :value="(bool) $v" />@break
                        @case('secret')
                            <div class="field">
                                <label class="field__label" for="f_{{ $name }}">{{ $label }}</label>
                                <input id="f_{{ $name }}" type="password" name="{{ $name }}" autocomplete="new-password" placeholder="{{ $settings->secret($key) ? '•••••••• (salvată; lăsați gol ca să o păstrați)' : 'nesetată' }}">
                                @if ($settings->secret($key))<label class="check small"><input type="checkbox" name="{{ $name }}__sterge" value="1"><span>Șterge valoarea salvată</span></label>@endif
                            </div>
                            @break
                        @case('money')<x-panou.field :name="$name" :label="$label" :value="$v !== null ? number_format($v / 100, 2, ',', '') : ''" :required="($f[2] ?? null) === 'required'" />@break
                        @case('percent')<x-panou.field :name="$name" :label="$label" :value="$v !== null ? rtrim(rtrim(number_format($v / 100, 2, ',', ''), '0'), ',') : ''" :required="($f[2] ?? null) === 'required'" />@break
                        @case('select')<x-panou.field :name="$name" type="select" :label="$label" :options="$f[3]" :value="$v" :placeholder="false" />@break
                        @case('textarea')<x-panou.field :name="$name" type="textarea" :label="$label" :value="$v" :full="true" />@break
                        @default<x-panou.field :name="$name" :type="in_array($type, ['email', 'tel', 'url', 'number'], true) ? $type : 'text'" :label="$label" :value="$v" :required="($f[2] ?? null) === 'required'" />
                    @endswitch
                @endforeach
                </div>
            </x-panou.panel>
        @endforeach
        <div class="form-actions"><button class="btn btn--primary" type="submit">Salvează</button></div>
    </form>
    @if ($section === 'plati')
        <form method="post" action="{{ route('panou.settings.test-bt') }}" style="margin-top:1rem">@csrf<button class="btn" type="submit">Testează conexiunea BT iPay</button></form>
    @endif
@endsection
