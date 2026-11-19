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
                @if ($section === 'plati' && str_contains($title, 'Stripe'))
                    @if ($stripeStatus)<div class="alert alert--warn">{{ $stripeStatus }}</div>@else<div class="alert alert--ok">Datele Stripe sunt completate (mod {{ $stripeMode === 'live' ? 'live — plăți reale' : 'test' }}). Testați conexiunea înainte de a activa plata.</div>@endif
                    <ol class="small muted">
                        <li>În contul Stripe: Developers → API keys → copiați „Secret key” (sk_live_…; pentru probe, sk_test_…) în câmpul de mai jos.</li>
                        <li>Developers → Webhooks → „Add endpoint”, cu adresa <code>{{ url('/webhook/plati/stripe') }}</code> și evenimentele <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>, <code>checkout.session.expired</code>; copiați „Signing secret” (whsec_…) mai jos.</li>
                        <li>Salvați, apăsați „Testează conexiunea Stripe”, apoi bifați „Plata prin Stripe este activă”.</li>
                    </ol>
                    <p class="small muted">Plata se confirmă numai după verificarea semnăturii Stripe și interogarea directă a plății la Stripe, niciodată din revenirea clientului pe site. Datele cardului nu trec prin site.</p>
                @endif
                @if ($section === 'plati' && str_contains($title, 'iPay') && $lastCardFailure && $lastCardFailure->provider === 'btipay')
                    <div class="alert alert--warn">Ultima încercare de plată cu cardul ({{ $lastCardFailure->created_at->format('d.m.Y H:i') }}) nu a pornit: {{ $lastCardFailure->failure_reason }}</div>
                @endif
                @if ($section === 'plati' && str_contains($title, 'Stripe') && $lastCardFailure && $lastCardFailure->provider === 'stripe')
                    <div class="alert alert--warn">Ultima încercare de plată prin Stripe ({{ $lastCardFailure->created_at->format('d.m.Y H:i') }}) nu a pornit: {{ $lastCardFailure->failure_reason }}</div>
                @endif
                @if ($section === 'plati' && str_contains($title, 'iPay'))
                    <p class="small muted">Completați „Utilizator API” și „Parola API” primite de la BT, alegeți mediul (Test pentru probe, Producție pentru plăți reale), salvați, apăsați „Testează conexiunea BT iPay”, apoi bifați „Plata cu cardul este activă”.</p>
                @endif
                @if ($section === 'valute' && $title === 'Valute' && $rates)
                    <div class="alert alert--info">
                        @foreach (['EUR', 'HUF'] as $cur)
                            <div>{{ $rates->describe($cur) ?? $cur.': fără curs încă — prețurile rămân în lei până la primul curs BNR (sau completați cursul manual).' }}</div>
                        @endforeach
                        @if ((int) ($values['currency.markup_bp'] ?? 0) > 0)<div class="small">Adaos aplicat: {{ number_format(((int) $values['currency.markup_bp']) / 100, 2, ',', '') }}%.</div>@endif
                    </div>
                @endif
                @if ($section === 'valute' && str_starts_with($title, 'Limba'))
                    @php($geoAt = \App\Modules\Regions\Services\GeoCountry::installedAt())
                    <p class="small muted">Țara se află din adresa IP, cu baza gratuită „IP to Country Lite” de la <a href="https://db-ip.com" rel="noopener" target="_blank">DB-IP</a> (licență CC BY 4.0), păstrată pe server și actualizată lunar; adresa IP nu se trimite nicăieri și nu se salvează. Baza instalată: {{ $geoAt ? date('d.m.Y', $geoAt) : 'încă nu (se descarcă automat; puteți apăsa „Descarcă acum”)' }}. Roboții motoarelor de căutare nu sunt redirecționați.</p>
                @endif
                @if ($section === 'email' && $mailStatus)
                    <div class="alert alert--{{ $mailStatus[0] }}">{{ $mailStatus[1] }}</div>
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
                        @case('emails')<x-panou.field :name="$name" :label="$label" :value="$v" :full="true" />@break
                        @case('rate')<x-panou.field :name="$name" :label="$label" :value="$v" />@break
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
        <div class="cluster" style="margin-top:1rem">
            <form method="post" action="{{ route('panou.settings.test-bt') }}">@csrf<button class="btn" type="submit">Testează conexiunea BT iPay</button></form>
            <form method="post" action="{{ route('panou.settings.test-stripe') }}">@csrf<button class="btn" type="submit">Testează conexiunea Stripe</button></form>
        </div>
    @endif
    @if ($section === 'valute')
        <div class="cluster" style="margin-top:1rem">
            <form method="post" action="{{ route('panou.settings.rates') }}">@csrf<button class="btn" type="submit">Citește acum cursul BNR</button></form>
            <form method="post" action="{{ route('panou.settings.geoip') }}">@csrf<button class="btn" type="submit">Descarcă acum baza de țări (DB-IP)</button></form>
        </div>
    @endif
    @if ($section === 'email')
        <form method="post" action="{{ route('panou.settings.test-mail') }}" style="margin-top:1rem">@csrf<button class="btn" type="submit">Trimite un email de test la {{ auth()->user()->email }}</button></form>
        <p class="small muted">Salvați întâi setările, apoi trimiteți testul.</p>
    @endif
@endsection
