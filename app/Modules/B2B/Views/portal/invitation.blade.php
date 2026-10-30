@extends('layouts.site')
@section('title', 'Invitație portal parteneri')
@section('robots', 'noindex, nofollow')
@section('content')
<div class="wrap page-pad"><div class="auth">
    <h1>Invitație</h1>
    @if (! $inv)
        <div class="notice notice--warn"><x-site.icon name="info" /><p>Invitația nu mai este valabilă (a expirat sau a fost deja folosită). Cereți administratorului organizației o invitație nouă.</p></div>
    @else
        <div class="box form">
            <p>Ați fost invitat(ă) în contul organizației <strong>{{ $inv->organization->name }}</strong>, cu rolul „{{ \App\Modules\B2B\Models\OrganizationMember::ROLES[$inv->role] ?? $inv->role }}”.</p>
            @if ($customer)
                <form method="post" action="/portal/invitatie/{{ $token }}/">@csrf<button class="btn btn--primary btn--block" type="submit">Acceptă ca {{ $customer->email }}</button></form>
                @error('email')<p class="error-text">{{ $message }}</p>@enderror
            @else
                <p class="small">Intrați în cont cu adresa <strong>{{ $inv->email }}</strong> sau creați un cont nou cu această adresă; apoi reveniți la acest link.</p>
                <form method="post" action="/portal/invitatie/{{ $token }}/">@csrf<button class="btn btn--primary btn--block" type="submit">Autentificare</button></form>
                <p class="small"><a href="/cont/inregistrare/?inapoi=/portal/invitatie/{{ $token }}/">Creează cont</a></p>
            @endif
        </div>
    @endif
</div></div>
@endsection
