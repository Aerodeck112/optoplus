@extends('customers::auth.shell')
@section('title', 'Parolă nouă')
@section('heading', 'Parolă nouă')
@section('form')
<form method="post" action="/cont/parola/reset/" class="box form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email', $email) }}"></div>
    <div class="field"><label for="parola">Parola nouă</label><input id="parola" name="parola" type="password" autocomplete="new-password" required minlength="10"><span class="hint">Minimum 10 caractere.</span></div>
    <div class="field"><label for="parola_confirmation">Repetă parola</label><input id="parola_confirmation" name="parola_confirmation" type="password" autocomplete="new-password" required></div>
    <button class="btn btn--primary btn--block" type="submit">Salvează parola</button>
</form>
@endsection
