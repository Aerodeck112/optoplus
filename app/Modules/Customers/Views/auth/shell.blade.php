{{-- Cadrul paginilor de autentificare: o coloană îngustă, fără elemente care distrag. --}}
@extends('layouts.site')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap page-pad">
    <div class="auth">
        <h1>@yield('heading')</h1>
        @if (session('status'))<div class="notice notice--ok" role="status"><x-site.icon name="check" /><p>{{ session('status') }}</p></div>@endif
        @if ($errors->any())<div class="notice notice--error" role="alert" tabindex="-1" data-focus-on-load><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @yield('form')
    </div>
</div>
@endsection
