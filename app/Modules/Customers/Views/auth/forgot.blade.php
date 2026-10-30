@extends('customers::auth.shell')
@section('title', 'Setează parola')
@section('heading', 'Setează o parolă nouă')
@section('form')
<form method="post" action="/cont/parola/" class="box form">
    @csrf
    <p>Scrieți adresa de email a contului. Vă trimitem un link pentru setarea parolei.</p>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"></div>
    <button class="btn btn--primary btn--block" type="submit">Trimite linkul</button>
</form>
@endsection
