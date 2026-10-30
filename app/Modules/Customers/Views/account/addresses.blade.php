@extends('customers::account.shell')
@section('title', 'Adresele mele')
@section('heading', 'Adrese')
@section('body')
<div class="layout-2">
    <div>
        @forelse ($addresses as $a)
            <article class="box line-flat">
                <div><strong>{{ $a->name }}</strong>@if($a->is_default) <span class="tag">implicită</span>@endif
                    <p class="small muted">@if($a->company){{ $a->company }} ({{ $a->vat_code }}) · @endif{{ $a->line1 }}, {{ $a->city }}, {{ $a->county }} {{ $a->postal_code }}@if($a->phone) · {{ $a->phone }}@endif</p></div>
                <form method="post" action="/cont/adrese/{{ $a->id }}/sterge/">@csrf<button class="link-btn" type="submit">Șterge</button></form>
            </article>
        @empty
            <div class="empty"><p>Nu aveți adrese salvate.</p></div>
        @endforelse
    </div>
    <form method="post" action="/cont/adrese/" class="box form">
        @csrf
        <h2>Adaugă o adresă</h2>
        <div class="field"><label for="nume">Nume</label><input id="nume" name="nume" required autocomplete="name" value="{{ old('nume') }}"></div>
        <div class="field"><label for="adresa">Adresa</label><input id="adresa" name="adresa" required autocomplete="street-address" value="{{ old('adresa') }}"></div>
        <div class="grid-2">
            <div class="field"><label for="localitate">Localitatea</label><input id="localitate" name="localitate" required value="{{ old('localitate') }}"></div>
            <div class="field"><label for="judet">Județul</label><input id="judet" name="judet" required value="{{ old('judet') }}"></div>
            <div class="field"><label for="cod_postal">Cod poștal</label><input id="cod_postal" name="cod_postal" value="{{ old('cod_postal') }}"></div>
            <div class="field"><label for="telefon">Telefon</label><input id="telefon" name="telefon" type="tel" value="{{ old('telefon') }}"></div>
            <div class="field"><label for="firma">Firmă <span class="hint">(opțional)</span></label><input id="firma" name="firma" value="{{ old('firma') }}"></div>
            <div class="field"><label for="cui">CUI <span class="hint">(opțional)</span></label><input id="cui" name="cui" value="{{ old('cui') }}"></div>
        </div>
        <label class="check"><input type="checkbox" name="implicita" value="1"><span>Folosește ca adresă implicită</span></label>
        <button class="btn btn--primary" type="submit">Salvează adresa</button>
    </form>
</div>
@endsection
