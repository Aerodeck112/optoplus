{{-- Acțiunile rapide pentru un cont: retrimiterea invitației / a linkului de parolă și ștergerea sau dezactivarea. --}}
<div class="row-actions__inner">
    <form method="post" action="{{ route('panou.users.send-reset', $u) }}">@csrf<button class="btn btn--small" type="submit" title="{{ $u->last_login_at ? 'Trimite pe email un link pentru setarea unei parole noi' : 'Trimite din nou emailul pentru setarea parolei' }}">{{ $u->last_login_at ? 'Resetare parolă' : 'Retrimite invitația' }}</button></form>
    @unless ($u->is(auth()->user()))
        @if (! $u->last_login_at)
            <form method="post" action="{{ route('panou.users.destroy', $u) }}" data-confirm="Ștergeți definitiv contul pentru {{ $u->name }}?">@csrf @method('delete')<button class="btn btn--small btn--danger" type="submit">Șterge</button></form>
        @elseif ($u->is_active)
            <form method="post" action="{{ route('panou.users.deactivate', $u) }}" data-confirm="Dezactivați contul pentru {{ $u->name }}? Persoana nu se va mai putea autentifica; istoricul rămâne.">@csrf<button class="btn btn--small btn--danger" type="submit">Dezactivează</button></form>
        @endif
    @endunless
</div>
