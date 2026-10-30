@extends('customers::account.shell')
@section('title', 'Datele mele')
@section('heading', 'Datele mele')
@section('body')
<div class="layout-2">
    <form method="post" action="/cont/date/" class="box form">
        @csrf
        <h2>Profil</h2>
        <div class="grid-2">
            <div class="field"><label for="prenume">Prenume</label><input id="prenume" name="prenume" required value="{{ old('prenume', $customer->first_name) }}"></div>
            <div class="field"><label for="nume">Nume</label><input id="nume" name="nume" required value="{{ old('nume', $customer->last_name) }}"></div>
        </div>
        <div class="field"><span class="label">Email</span><span>{{ $customer->email }}</span></div>
        <div class="field"><label for="telefon">Telefon</label><input id="telefon" name="telefon" type="tel" value="{{ old('telefon', $customer->phone) }}"></div>
        <label class="check"><input type="checkbox" name="marketing" value="1" @checked($customer->marketing_consent_at)><span>Primesc pe email noutăți și oferte. Acordul este separat de emailurile despre comenzi, care se trimit oricum.</span></label>
        <button class="btn btn--primary" type="submit">Salvează</button>
    </form>
    <div>
        <section class="box">
            <h2>Datele dumneavoastră</h2>
            <p class="small">Puteți descărca oricând datele din cont (profil, adrese, comenzi, rețete, acorduri), într-un fișier JSON.</p>
            <a class="btn btn--small" href="/cont/date/export/">Descarcă datele mele</a>
        </section>
        <section class="box">
            <h2>Ștergerea contului</h2>
            <p class="small">Ștergem contul și datele personale. Datele pe care legea ne obligă să le păstrăm (de exemplu facturile și comenzile aferente) se păstrează doar pe durata cerută.</p>
            <form method="post" action="/cont/date/stergere/" class="form">
                @csrf
                <label class="check"><input type="checkbox" name="confirmare" value="1"><span>Confirm că vreau ștergerea contului.</span></label>
                <button class="btn btn--small" type="submit">Cer ștergerea</button>
            </form>
            @foreach ($requests as $r)<p class="small muted">Cerere de {{ $r->type === 'stergere' ? 'ștergere' : 'export' }} din {{ $r->created_at->format('d.m.Y') }} — {{ ['noua' => 'înregistrată', 'in_lucru' => 'în lucru', 'finalizata' => 'finalizată', 'respinsa' => 'respinsă'][$r->status] ?? $r->status }}</p>@endforeach
        </section>
    </div>
</div>
@endsection
