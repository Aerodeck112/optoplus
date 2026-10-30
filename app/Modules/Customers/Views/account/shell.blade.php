{{-- Cadrul contului: navigația secțiunilor, mesaje. Paginile nu se cache-uiesc. --}}
@extends('layouts.site')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap page-pad">
    <div class="account-head">
        <h1>@yield('heading')</h1>
        <form method="post" action="/cont/iesire/">@csrf<button class="link-btn" type="submit">Ieșire din cont</button></form>
    </div>
    <nav class="account-nav" aria-label="Contul meu">
        @foreach (['/cont/' => 'Comenzi', '/cont/configuratii/' => 'Configurații salvate', '/cont/retete/' => 'Rețete', '/cont/adrese/' => 'Adrese', '/cont/date/' => 'Datele mele'] as $url => $label)
            <a href="{{ $url }}" @if('/'.trim(request()->path(), '/').'/' === $url) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if (session('status'))<div class="notice notice--ok" role="status"><x-site.icon name="check" /><p>{{ session('status') }}</p></div>@endif
    @if ($errors->any())<div class="notice notice--error" role="alert"><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('body')
</div>
@endsection
