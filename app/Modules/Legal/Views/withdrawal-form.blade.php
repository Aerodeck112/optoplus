@extends('layouts.site')
@section('title', 'Retrageți-vă din contract aici')
@section('robots', 'noindex, follow')
@section('content')
<div class="wrap page-pad legal-flow">
    <h1>Retrageți-vă din contract aici</h1>
    <p class="lead">Puteți anunța online că vă retrageți dintr-o comandă, fără să ne scrieți separat. Completați datele de mai jos; la pasul următor confirmați retragerea, iar noi vă trimitem imediat pe email confirmarea de primire, cu data și ora.</p>
    <div class="notice notice--warn"><x-site.icon name="info" /><div>
        <p><strong>Ce se poate returna:</strong> ramele cumpărate fără lentile și accesoriile, în 14 zile de la primire, fără să indicați motivul.</p>
        <p>Lentilele și ochelarii făcuți după rețeta și alegerile dumneavoastră sunt produse personalizate și nu fac obiectul dreptului de retragere. Dacă un produs are o problemă, se aplică garanția legală de conformitate (<a href="/garantie-legala/">detalii</a>).</p>
    </div></div>
    @if ($errors->any())<div class="notice notice--error" role="alert"><x-site.icon name="warn" /><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <form method="post" action="/retragere/" class="box form" novalidate>
        @csrf
        <div class="grid-2">
            <div class="field"><label for="nume">Numele dumneavoastră</label><input id="nume" name="nume" autocomplete="name" required value="{{ old('nume', $draft['nume']) }}"></div>
            <div class="field"><label for="comanda">Numărul comenzii</label><input id="comanda" name="comanda" required value="{{ old('comanda', $draft['comanda']) }}" placeholder="ex. OP-2026-00123"><span class="hint">Îl găsiți în emailul de confirmare a comenzii.</span></div>
            <div class="field"><label for="email">Emailul la care primiți confirmarea</label><input id="email" type="email" name="email" autocomplete="email" required value="{{ old('email', $draft['email']) }}"><span class="hint">De preferat, emailul folosit la comandă.</span></div>
        </div>
        <div class="field"><label for="detalii">Ce produse returnați <span class="hint">(opțional)</span></label><textarea id="detalii" name="detalii" rows="3" maxlength="2000">{{ old('detalii', $draft['detalii']) }}</textarea></div>
        <button class="btn btn--primary" type="submit">Continuă</button>
        <p class="small muted">Preferați varianta clasică? Puteți folosi <a href="/formular-retragere/">formularul tip de retragere</a> sau ne puteți scrie orice declarație neechivocă.</p>
    </form>
</div>
@endsection
