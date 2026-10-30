@extends('customers::auth.shell')
@section('title', 'Autentificare')
@section('heading', 'Contul meu')
@section('form')
<form method="post" action="/cont/autentificare/" class="box form">
    @csrf
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}"></div>
    <div class="field"><label for="parola">Parola</label><input id="parola" name="parola" type="password" autocomplete="current-password" required></div>
    <label class="check"><input type="checkbox" name="tine_minte" value="1"><span>Ține-mă minte pe acest dispozitiv</span></label>
    <button class="btn btn--primary btn--block" type="submit">Intră în cont</button>
    <p class="small"><a href="/cont/parola/">Ați uitat parola?</a></p>
</form>
<div class="box">
    <h2>Ați avut cont pe vechiul site?</h2>
    <p class="small">Conturile au fost mutate. Încercați cu parola de pe vechiul site; dacă nu funcționează, <a href="/cont/parola/">setați o parolă nouă</a> cu adresa de email a contului.</p>
    <p class="small">Nu aveți cont? <a href="/cont/inregistrare/">Creați unul</a> — sau comandați fără cont.</p>
</div>
@endsection
