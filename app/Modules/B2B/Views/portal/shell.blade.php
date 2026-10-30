{{-- Cadrul portalului pentru parteneri: organizația, navigația după rol, mesaje. --}}
@extends('layouts.site')
@section('robots', 'noindex, nofollow')
@section('body_class', 'portal-page')
@section('content')
<div class="wrap page-pad">
    <div class="account-head">
        <div>
            <p class="eyebrow">Portal parteneri · {{ $org->name }}</p>
            <h1>@yield('heading')</h1>
        </div>
        <form method="post" action="/cont/iesire/">@csrf<button class="link-btn" type="submit">Ieșire</button></form>
    </div>
    <nav class="account-nav" aria-label="Portal parteneri">
        @php($nav = ['/portal/' => ['Panou', 'view'], '/portal/comanda-rapida/' => ['Comandă rapidă', 'order'], '/portal/import/' => ['Import CSV', 'order'], '/portal/cos/' => ['Coș', 'order'], '/portal/comenzi/' => ['Comenzi', 'view'], '/portal/oferte/' => ['Oferte', 'view'], '/portal/documente/' => ['Documente', 'documents'], '/portal/utilizatori/' => ['Utilizatori', 'manage_users']])
        @foreach ($nav as $url => [$label, $ability])
            @if ($partner->can($ability))<a href="{{ $url }}" @if('/'.trim(request()->path(), '/').'/' === $url) aria-current="page" @endif>{{ $label }}</a>@endif
        @endforeach
    </nav>
    @if (session('status'))<div class="notice notice--ok" role="status"><x-site.icon name="check" /><p>{{ session('status') }}</p></div>@endif
    @if ($errors->any())<div class="notice notice--error" role="alert" tabindex="-1" data-focus-on-load><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('body')
</div>
@endsection
