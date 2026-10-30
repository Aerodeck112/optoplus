@extends('customers::auth.shell')
@section('title', 'Creează cont')
@section('heading', 'Creează cont')
@section('form')
<form method="post" action="/cont/inregistrare/" class="box form">
    @csrf
    <div class="grid-2">
        <div class="field"><label for="prenume">Prenume</label><input id="prenume" name="prenume" autocomplete="given-name" required value="{{ old('prenume') }}"></div>
        <div class="field"><label for="nume">Nume</label><input id="nume" name="nume" autocomplete="family-name" required value="{{ old('nume') }}"></div>
    </div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"></div>
    <div class="field"><label for="telefon">Telefon <span class="hint">(opțional)</span></label><input id="telefon" name="telefon" type="tel" autocomplete="tel" value="{{ old('telefon') }}"></div>
    <div class="field"><label for="parola">Parola</label><input id="parola" name="parola" type="password" autocomplete="new-password" required minlength="10"><span class="hint">Minimum 10 caractere.</span></div>
    <div class="field"><label for="parola_confirmation">Repetă parola</label><input id="parola_confirmation" name="parola_confirmation" type="password" autocomplete="new-password" required></div>
    <label class="check"><input type="checkbox" name="acord_termeni" value="1" required @checked(old('acord_termeni'))><span>Accept <a href="/termeni-si-conditii/" target="_blank" rel="noopener">termenii</a> și am citit <a href="/politica-de-confidentialitate/" target="_blank" rel="noopener">politica de confidențialitate</a>.</span></label>
    <label class="check"><input type="checkbox" name="marketing" value="1" @checked(old('marketing'))><span>Vreau să primesc pe email noutăți și oferte (opțional; vă puteți dezabona oricând).</span></label>
    <button class="btn btn--primary btn--block" type="submit">Creează contul</button>
    <p class="small">Aveți deja cont? <a href="/cont/autentificare/">Autentificați-vă</a>.</p>
</form>
@endsection
