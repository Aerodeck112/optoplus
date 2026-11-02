{{-- Autentificarea partenerilor: panou de prezentare cu macheta portalului + formularul. --}}
@extends('layouts.site')
@section('title', 'Autentificare parteneri')
@section('robots', 'noindex, nofollow')
@section('body_class', 'portal-login-page')
@section('content')
<div class="partner-login">
    <div class="wrap partner-login__grid">
        <div class="partner-login__intro">
            <p class="eyebrow"><x-site.icon name="building" :size="16" />Portal parteneri Optoplus</p>
            <h1>Lentile direct din laborator, pentru pacienții dumneavoastră.</h1>
            <ul class="partner-login__points">
                <li><x-site.icon name="lens" :size="20" /><span><strong>Comandă rapidă</strong> mai multe lucrări odată, cu codul intern al pacientului</span></li>
                <li><x-site.icon name="clock" :size="20" /><span><strong>Urmărire pas cu pas</strong> de la verificarea rețetei până la expediere</span></li>
                <li><x-site.icon name="file" :size="20" /><span><strong>Oferte și documente</strong> într-un singur loc, pentru toată echipa</span></li>
            </ul>
            <div class="partner-login__visual">
                @include('b2b::mock.screen', ['screen' => 'tracking'])
            </div>
        </div>
        <div class="partner-login__box">
            <h2>Autentificare</h2>
            @if ($loggedIn)
                <div class="notice" role="status"><x-site.icon name="info" /><p>Contul cu care sunteți conectat nu face parte dintr-o organizație parteneră. Folosiți linkul din invitație sau trimiteți o <a href="/parteneri/#cerere">cerere de parteneriat</a>.</p></div>
            @endif
            @if (session('status'))<div class="notice notice--ok" role="status"><x-site.icon name="check" /><p>{{ session('status') }}</p></div>@endif
            @if ($errors->any())<div class="notice notice--error" role="alert" tabindex="-1" data-focus-on-load><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            <form method="post" action="/cont/autentificare/" class="form">
                @csrf
                <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}"></div>
                <div class="field"><label for="parola">Parola</label><input id="parola" name="parola" type="password" autocomplete="current-password" required></div>
                <label class="check"><input type="checkbox" name="tine_minte" value="1"><span>Ține-mă minte pe acest dispozitiv</span></label>
                <button class="btn btn--primary btn--block" type="submit">Intră în portal</button>
                <p class="small"><a href="/cont/parola/">Ați uitat parola?</a></p>
            </form>
            <div class="partner-login__help">
                <p><strong>Ați primit o invitație?</strong> Deschideți linkul din email ca să vă creați accesul.</p>
                <p><strong>Nu sunteți încă partener?</strong> <a href="/parteneri/#cerere">Trimiteți o cerere de parteneriat</a>.</p>
            </div>
        </div>
    </div>
</div>
@endsection
