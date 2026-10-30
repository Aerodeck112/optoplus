@extends('b2b::portal.shell')
@section('title', 'Utilizatori')
@section('heading', 'Utilizatorii organizației')
@section('body')
<div class="layout-2">
    <div class="box table-wrap">
        <table class="table">
            <thead><tr><th>Utilizator</th><th>Rol</th><th></th></tr></thead>
            <tbody>
            @foreach ($members as $m)
                <tr><td>{{ $m->customer->fullName() }}<br><small class="muted">{{ $m->customer->email }}</small></td>
                    <td>@if($m->customer_id === $partner->customer->id){{ \App\Modules\B2B\Models\OrganizationMember::ROLES[$m->role] ?? $m->role }} (dvs.)@else
                        <form method="post" action="/portal/utilizatori/{{ $m->id }}/" class="inline-field">@csrf
                            <select name="rol" aria-label="Rolul pentru {{ $m->customer->email }}">@foreach (\App\Modules\B2B\Models\OrganizationMember::ROLES as $k => $l)<option value="{{ $k }}" @selected($m->role === $k)>{{ $l }}</option>@endforeach</select>
                            <button class="btn btn--small" type="submit">Salvează</button></form>@endif</td>
                    <td>@unless($m->customer_id === $partner->customer->id)<form method="post" action="/portal/utilizatori/{{ $m->id }}/">@csrf<button class="link-btn" name="actiune" value="elimina">Elimină</button></form>@endunless</td></tr>
            @endforeach
            @foreach ($invitations as $i)<tr><td>{{ $i->email }}</td><td>invitație trimisă · {{ \App\Modules\B2B\Models\OrganizationMember::ROLES[$i->role] ?? $i->role }}</td><td class="small muted">expiră {{ $i->expires_at->format('d.m.Y') }}</td></tr>@endforeach
            </tbody>
        </table>
    </div>
    <form method="post" action="/portal/utilizatori/" class="box form">
        @csrf
        <h2>Invită un coleg</h2>
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required></div>
        <div class="field"><label for="rol">Rol</label><select id="rol" name="rol">@foreach (\App\Modules\B2B\Models\OrganizationMember::ROLES as $k => $l)<option value="{{ $k }}" @selected($k === 'comenzi')>{{ $l }}</option>@endforeach</select></div>
        <button class="btn btn--primary" type="submit">Trimite invitația</button>
    </form>
</div>
@endsection
